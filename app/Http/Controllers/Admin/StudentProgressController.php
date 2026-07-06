<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\BuildsProtectedMediaUrls;
use App\Http\Controllers\Controller;
use App\Events\EmailNotifications\AssignmentApproved;
use App\Events\EmailNotifications\AssignmentRejected;
use App\Events\EmailNotifications\CertificateCreated;
use App\Mail\StudentProgressActionMail;
use App\Models\AccessTier;
use App\Models\AssignmentSubmission;
use App\Models\Assignment;
use App\Models\Certificate;
use App\Models\LessonProgress;
use App\Models\Module;
use App\Models\User;
use App\Services\Certificates\CertificateEligibilityService;
use App\Services\Certificates\CertificateGeneratorService;
use App\Services\BunnyStorageService;
use App\Services\StudentLearningPathService;
use App\Services\StudentSessionTrackingService;
use App\Support\BunnyAssetPath;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class StudentProgressController extends Controller
{
    use BuildsProtectedMediaUrls;

    public function __construct(
        private readonly StudentSessionTrackingService $sessionTrackingService,
        private readonly BunnyStorageService $bunnyStorage,
        private readonly CertificateEligibilityService $certificateEligibilityService,
        private readonly CertificateGeneratorService $certificateGeneratorService,
        private readonly StudentLearningPathService $studentLearningPathService,
    ) {}

    public function completedLessonsIndex(): RedirectResponse
    {
        return to_route('admin.student-progress.index');
    }

    public function index(): Response
    {
        $students = User::query()
            ->with([
                'accessTier:id,name,slug',
                'lessonProgresses' => fn ($query) => $query
                    ->where('is_done', true)
                    ->select('id', 'user_id', 'lesson_id'),
                'assignmentSubmissions:id,user_id,assignment_id,assignment_type,assignment_video,submitted_at',
            ])
            ->withMax('userSessions as last_login_at', 'login_at')
            ->where('role', User::ROLE_STUDENT)
            ->whereNotNull('access_tier_id')
            ->orderByDesc('created_at')
            ->get([
                'id',
                'name',
                'first_name',
                'last_name',
                'email',
                'access_tier_id',
                'profile_photo',
                'created_at',
            ]);

        $tiers = AccessTier::query()
            ->whereIn('slug', [
                AccessTier::SLUG_MASTER_CLASS,
                AccessTier::SLUG_ONLINE,
                AccessTier::SLUG_STARTER_KIT,
            ])
            ->get(['id', 'name', 'slug'])
            ->keyBy('id');

        $modules = Module::query()
            ->with([
                'accessTiers:id,slug',
                'lessons' => fn ($query) => $query
                    ->with('accessTiers:id,slug')
                    ->select('id', 'module_id', 'title'),
            ])
            ->whereHas('accessTiers', fn ($query) => $query->whereIn('slug', [
                AccessTier::SLUG_MASTER_CLASS,
                AccessTier::SLUG_ONLINE,
                AccessTier::SLUG_STARTER_KIT,
            ]))
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get(['id', 'title', 'sort_order']);

        return Inertia::render('Admin/StudentProgress/Directory', [
            'tierSections' => $this->buildStudentsByTierPayload($students, $tiers, $modules),
            'status' => session('status'),
        ]);
    }

    public function assignmentsIndex(): RedirectResponse
    {
        return to_route('admin.student-progress.index');
    }

    public function certificatesIndex(): RedirectResponse
    {
        return to_route('admin.student-progress.index');
    }

    public function showStudentDetail(User $student): Response
    {
        $student = $this->resolveStudent($student);

        return Inertia::render('Admin/Students/Edit', [
            'student' => $this->studentDetailPayload($student),
            'accessTiers' => $this->accessTierOptions(),
            'managementContext' => 'student_progress',
            'status' => session('status'),
        ]);
    }

    public function showCompletedLessons(User $student): Response
    {
        $student = $this->resolveStudent($student);

        return Inertia::render('Admin/StudentProgress/CompletedLessons', [
            'student' => $this->studentPayload($student),
            'completedLessons' => $this->completedLessonsPayload($student),
            'status' => session('status'),
        ]);
    }

    public function showAssignments(User $student): Response
    {
        $student = $this->resolveStudent($student);

        return Inertia::render('Admin/StudentProgress/Assignments', [
            'student' => $this->studentPayload($student),
            'assignments' => $this->assignmentsPayload($student),
            'assignmentStatuses' => collect(AssignmentSubmission::STATUSES)
                ->map(fn (string $status) => [
                    'value' => $status,
                    'label' => str($status)->replace('_', ' ')->title()->value(),
                ])
                ->values(),
            'status' => session('status'),
        ]);
    }

    public function showCertificates(User $student): Response
    {
        $student = $this->resolveStudent($student);

        return Inertia::render('Admin/StudentProgress/Certificates', [
            'student' => $this->studentPayload($student),
            'certificateRows' => $this->certificateEligibilityService->rowsForStudent($student),
            'certificateReadiness' => $this->certificateReadinessPayload($student),
            'status' => session('status'),
        ]);
    }

    public function resetLesson(User $student, LessonProgress $lessonProgress): RedirectResponse
    {
        $student = $this->resolveStudent($student);
        abort_unless($lessonProgress->user_id === $student->id, 404);

        $lessonProgress->update([
            'watch_progress' => 0,
            'is_workbook_downloaded' => false,
            'workbook_downloaded_at' => null,
            'video_completed_at' => null,
            'is_done' => false,
            'completed_at' => null,
        ]);

        return redirect()
            ->route('admin.student-progress.completed-lessons.show', $student)
            ->with('status', 'student-progress-lesson-reset');
    }

    public function updateAssignment(Request $request, User $student, AssignmentSubmission $assignmentSubmission): RedirectResponse
    {
        $student = $this->resolveStudent($student);
        abort_unless($assignmentSubmission->user_id === $student->id, 404);

        $data = $request->validate([
            'assignment_status' => ['required', 'in:'.implode(',', AssignmentSubmission::STATUSES)],
            'assignment_feedback' => ['nullable', 'string'],
        ]);

        $previousStatus = $assignmentSubmission->assignment_status;
        $assignmentSubmission->fill($data);
        $assignmentSubmission->graded_at = in_array(
            $assignmentSubmission->assignment_status,
            [AssignmentSubmission::STATUS_APPROVED, AssignmentSubmission::STATUS_REJECTED],
            true,
        ) ? now() : null;
        $assignmentSubmission->reviewed_at = $assignmentSubmission->graded_at;
        $assignmentSubmission->reviewed_by = $assignmentSubmission->graded_at ? auth()->id() : null;
        $assignmentSubmission->save();

        $emailPayload = [
            'user_name' => $student->name,
            'user_email' => $student->email,
            'assignment_type' => $assignmentSubmission->emailTypeLabel(),
            'feedback' => $assignmentSubmission->assignment_feedback,
            'admin_email' => config('mail.from.address'),
            'dashboard_url' => route('student.dashboard'),
        ];

        if (
            $previousStatus !== AssignmentSubmission::STATUS_APPROVED
            && $assignmentSubmission->assignment_status === AssignmentSubmission::STATUS_APPROVED
        ) {
            event(new AssignmentApproved(
                $emailPayload,
                'assignment_submission',
                $assignmentSubmission->id,
            ));
        }

        if ($assignmentSubmission->assignment_status === AssignmentSubmission::STATUS_REJECTED) {
            event(new AssignmentRejected(
                $emailPayload,
                'assignment_submission',
                $assignmentSubmission->id,
            ));
        }

        return redirect()
            ->route('admin.student-progress.assignments.show', $student)
            ->with('status', 'student-progress-assignment-saved');
    }

    public function sendAssignmentEmail(User $student, AssignmentSubmission $assignmentSubmission): RedirectResponse
    {
        $student = $this->resolveStudent($student);
        abort_unless($assignmentSubmission->user_id === $student->id, 404);

        $this->sendEmail(
            $student,
            'Your YogaFX assignment update',
            'Assignment Update',
            [
                'Assignment: '.$assignmentSubmission->title(),
                'Status: '.str($assignmentSubmission->assignment_status)->replace('_', ' ')->title()->value(),
                $assignmentSubmission->assignment_feedback
                    ? 'Feedback: '.$assignmentSubmission->assignment_feedback
                    : 'No additional feedback was provided.',
            ],
        );

        return redirect()
            ->route('admin.student-progress.assignments.show', $student)
            ->with('status', 'student-progress-assignment-email-sent');
    }

    public function deleteAssignmentVideo(User $student, AssignmentSubmission $assignmentSubmission): RedirectResponse
    {
        $student = $this->resolveStudent($student);
        abort_unless($assignmentSubmission->user_id === $student->id, 404);

        if (filled($assignmentSubmission->assignment_video)) {
            $this->bunnyStorage->delete($assignmentSubmission->assignment_video);
        }

        $assignmentSubmission->delete();

        return redirect()
            ->route('admin.student-progress.assignments.show', $student)
            ->with('status', 'student-progress-assignment-video-deleted');
    }

    public function generateCertificate(Request $request, User $student): RedirectResponse
    {
        $student = $this->resolveStudent($student);
        $data = $request->validate([
            'certificate_type' => ['required', 'string'],
        ]);

        $row = collect($this->certificateEligibilityService->rowsForStudent($student))
            ->firstWhere('type', $data['certificate_type']);

        abort_if(! $row, 422, 'This certificate type is not available for the student active tier.');
        abort_if(! $row['can_generate'], 422, $row['action_block_reason']);

        $certificate = $this->certificateGeneratorService->generate(
            $student,
            $data['certificate_type'],
            auth()->id(),
        );

        event(new CertificateCreated([
            'user_name' => $student->name,
            'user_email' => $student->email,
            'certificate_type' => $certificate->typeLabel(),
            'certificate_file_name' => $certificate->file_name,
        ], 'certificate', $certificate->id));

        return redirect()
            ->route('admin.student-progress.certificates.show', $student)
            ->with('status', 'student-progress-certificate-generated');
    }

    public function recreateCertificate(User $student, Certificate $certificate): RedirectResponse
    {
        $student = $this->resolveStudent($student);
        abort_unless($certificate->user_id === $student->id, 404);

        $row = collect($this->certificateEligibilityService->rowsForStudent($student))
            ->firstWhere('type', $certificate->certificate_type);

        abort_if(! $row, 422, 'This certificate type is not available for the student active tier.');
        abort_if(! $row['can_regenerate'], 422, $row['action_block_reason']);

        $regenerated = $this->certificateGeneratorService->generate(
            $student,
            $certificate->certificate_type,
            auth()->id(),
        );

        event(new CertificateCreated([
            'user_name' => $student->name,
            'user_email' => $student->email,
            'certificate_type' => $regenerated->typeLabel(),
            'certificate_file_name' => $regenerated->file_name,
        ], 'certificate', $regenerated->id));

        return redirect()
            ->route('admin.student-progress.certificates.show', $student)
            ->with('status', 'student-progress-certificate-recreated');
    }

    public function sendGraduationEmail(User $student): RedirectResponse
    {
        $student = $this->resolveStudent($student);
        $summary = $this->certificateEligibilityService->summaryForStudent($student);
        $certificates = $this->certificateEligibilityService
            ->latestCertificatesByType($student, $summary['available_types'])
            ->values();

        abort_unless($certificates->isNotEmpty(), 422);

        $this->sendEmail(
            $student,
            'Your YogaFX graduation certificate update',
            'Graduation Certificate Update',
            [
                'Your certificate records are ready in YogaFX LMS.',
                'Available certificates: '.$certificates
                    ->map(fn (Certificate $certificate) => $certificate->typeLabel())
                    ->join(', '),
            ],
        );

        $latestCertificate = $certificates->sortByDesc(fn (Certificate $certificate) => sprintf(
            '%010d-%010d',
            $certificate->generated_at?->getTimestamp() ?? 0,
            $certificate->id,
        ))->first();

        event(new CertificateCreated([
            'user_name' => $student->name,
            'user_email' => $student->email,
            'certificate_type' => $latestCertificate?->typeLabel(),
            'certificate_file_name' => $latestCertificate?->file_name,
        ], 'certificate', $latestCertificate?->id));

        return redirect()
            ->route('admin.student-progress.certificates.show', $student)
            ->with('status', 'student-progress-graduation-email-sent');
    }

    public function downloadCertificate(User $student, Certificate $certificate)
    {
        $student = $this->resolveStudent($student);
        abort_unless($certificate->user_id === $student->id, 404);

        if (BunnyAssetPath::isBunnyPath($certificate->file_path)) {
            $url = $this->bunnyStorage->url($certificate->file_path);
            abort_unless(filled($url), 404);

            return redirect()->away($url);
        }

        abort_unless(Storage::disk('local')->exists($certificate->file_path), 404);

        return Storage::disk('local')->download($certificate->file_path, $certificate->file_name);
    }

    public function destroyCertificate(User $student, Certificate $certificate): RedirectResponse
    {
        $student = $this->resolveStudent($student);
        abort_unless($certificate->user_id === $student->id, 404);

        $this->bunnyStorage->delete($certificate->file_path);

        $certificate->delete();

        return redirect()
            ->route('admin.student-progress.certificates.show', $student)
            ->with('status', 'student-progress-certificate-deleted');
    }

    private function studentPayload(User $student): array
    {
        return [
            'id' => $student->id,
            'name' => $student->name,
            'email' => $student->email,
            'role' => $student->role,
            'is_active' => $student->isStudentAccountActive(),
            'access_tier' => $student->accessTier ? [
                'id' => $student->accessTier->id,
                'name' => $student->accessTier->name,
                'slug' => $student->accessTier->slug,
                'is_active' => $student->accessTier->is_active,
            ] : null,
            'access_tier_slug' => $student->accessTier?->slug,
            'profile_is_complete' => $student->hasCompletedStudentProfile(),
            'access_time_summary' => $this->sessionTrackingService->summaryForUser(
                $student,
            ),
        ];
    }

    private function buildStudentsByTierPayload(
        EloquentCollection $students,
        Collection $tiers,
        EloquentCollection $modules,
    ): Collection {
        $sectionDefinitions = collect([
            AccessTier::SLUG_MASTER_CLASS => 'Masterclass',
            AccessTier::SLUG_ONLINE => 'Online',
            AccessTier::SLUG_STARTER_KIT => 'Starter Kit',
        ]);

        return $sectionDefinitions->map(function (string $label, string $slug) use ($students, $tiers, $modules) {
            $tier = $tiers->firstWhere('slug', $slug);
            $tierId = $tier?->id;

            return [
                'slug' => $slug,
                'label' => $label,
                'students' => $students
                    ->filter(fn (User $student) => $student->accessTier?->slug === $slug)
                    ->values()
                    ->map(fn (User $student, int $index) => [
                        'id' => $student->id,
                        'number' => $index + 1,
                        'name' => $student->name ?: trim("{$student->first_name} {$student->last_name}"),
                        'profile_photo' => $this->protectedMediaUrl(
                            'user',
                            $student->id,
                            'profile_photo',
                            $student->profile_photo,
                            versionSeed: $student->updated_at,
                        ),
                        'profile_initials' => $this->initialsFor($student),
                        'progress_percentage' => $this->progressPercentageForStudent($student, $modules, $tierId),
                        'registration_date' => optional($student->created_at)->format('Y-m-d'),
                        'registration_date_sort' => optional($student->created_at)?->toDateString(),
                        'last_visit_at' => $student->last_login_at
                            ? \Illuminate\Support\Carbon::parse($student->last_login_at)->format('Y-m-d H:i')
                            : 'Never logged in',
                        'last_visit_sort' => $student->last_login_at
                            ? \Illuminate\Support\Carbon::parse($student->last_login_at)->toIso8601String()
                            : null,
                    ]),
            ];
        })->values();
    }

    private function progressPercentageForStudent(User $student, EloquentCollection $modules, ?int $tierId): int
    {
        if (! $tierId) {
            return 0;
        }

        $completedLessonIds = $student->lessonProgresses
            ->pluck('lesson_id')
            ->map(fn ($lessonId) => (int) $lessonId)
            ->all();

        $completedModules = 0;
        $totalModules = 0;

        foreach ($modules as $module) {
            if (! $module->accessTiers->contains('id', $tierId)) {
                continue;
            }

            $lessonIds = $module->lessons
                ->filter(fn ($lesson) => $lesson->accessTiers->isEmpty() || $lesson->accessTiers->contains('id', $tierId))
                ->pluck('id')
                ->map(fn ($lessonId) => (int) $lessonId)
                ->values();

            if ($lessonIds->isEmpty()) {
                continue;
            }

            $totalModules++;

            if ($lessonIds->every(fn (int $lessonId) => in_array($lessonId, $completedLessonIds, true))) {
                $completedModules++;
            }
        }

        if ($totalModules === 0) {
            return 0;
        }

        return (int) round(($completedModules / $totalModules) * 100);
    }

    private function assignmentStatusForStudent(User $student): string
    {
        $requiredAssignmentIds = $this->studentLearningPathService->relevantAssignmentIdsForStudent($student);

        if ($requiredAssignmentIds->isEmpty()) {
            return 'Not Available';
        }

        $submittedAssignmentIds = $student->assignmentSubmissions
            ->filter(fn (AssignmentSubmission $submission) => $requiredAssignmentIds->contains((int) $submission->assignment_id))
            ->filter(fn (AssignmentSubmission $submission) => filled($submission->assignment_video) || filled($submission->submitted_at))
            ->pluck('assignment_id')
            ->map(fn ($assignmentId) => (int) $assignmentId)
            ->unique();

        return $submittedAssignmentIds->count() >= $requiredAssignmentIds->count()
            ? 'Submitted'
            : 'Not Submitted';
    }

    private function initialsFor(User $student): string
    {
        $source = trim($student->name ?: "{$student->first_name} {$student->last_name}");

        if ($source === '') {
            return 'ST';
        }

        return collect(preg_split('/\s+/', $source) ?: [])
            ->filter()
            ->take(2)
            ->map(fn (string $part) => str($part)->substr(0, 1)->upper()->value())
            ->implode('');
    }

    private function completedLessonsPayload(User $student)
    {
        return LessonProgress::query()
            ->with(['lesson.module'])
            ->where('user_id', $student->id)
            ->where('is_done', true)
            ->orderByDesc('completed_at')
            ->get()
            ->map(fn (LessonProgress $progress) => [
                'id' => $progress->id,
                'lesson_title' => $progress->lesson?->title ?? 'Unknown lesson',
                'module_title' => $progress->lesson?->module?->title ?? '-',
                'completed_at' => optional($progress->completed_at)->format('Y-m-d H:i'),
                'watch_progress' => (float) $progress->watch_progress,
            ]);
    }

    private function assignmentsPayload(User $student)
    {
        return AssignmentSubmission::query()
            ->with('assignment')
            ->where('user_id', $student->id)
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (AssignmentSubmission $assignment) => [
                'id' => $assignment->id,
                'title' => $assignment->title(),
                'video' => $this->protectedMediaUrl(
                    'assignment-submission',
                    $assignment->id,
                    'assignment_video',
                    $assignment->assignment_video,
                    versionSeed: $assignment->updated_at,
                ),
                'video_path' => $assignment->assignment_video,
                'status' => $assignment->assignment_status,
                'feedback' => $assignment->assignment_feedback,
                'submitted_at' => optional($assignment->submitted_at)->format('Y-m-d H:i'),
            ]);
    }

    private function certificateReadinessPayload(User $student): array
    {
        $summary = $this->certificateEligibilityService->summaryForStudent($student);
        $generatedCertificates = $this->certificateEligibilityService
            ->latestCertificatesByType($student, $summary['available_types']);

        return [
            'tier_name' => $summary['tier']['name'] ?? null,
            'message' => $summary['message'],
            'learning_eligible' => $summary['learning_eligible'],
            'has_required_name' => $summary['has_required_name'],
            'requirements' => $summary['requirements'],
            'generated_count' => $generatedCertificates->count(),
            'available_count' => count($summary['available_types']),
        ];
    }

    private function resolveStudent(User $student): User
    {
        abort_unless($student->isStudent(), 404);

        return $student->load('accessTier');
    }

    private function accessTierOptions(): Collection
    {
        return AccessTier::query()
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get()
            ->map(fn (AccessTier $accessTier) => [
                'id' => $accessTier->id,
                'name' => $accessTier->name,
                'slug' => $accessTier->slug,
                'is_active' => $accessTier->is_active,
            ]);
    }

    private function studentDetailPayload(User $student): array
    {
        return [
            'id' => $student->id,
            'name' => $student->name,
            'role' => $student->role,
            'is_active' => $student->isStudentAccountActive(),
            'access_tier_id' => $student->access_tier_id,
            'access_tier' => $student->accessTier ? [
                'id' => $student->accessTier->id,
                'name' => $student->accessTier->name,
                'slug' => $student->accessTier->slug,
                'is_active' => $student->accessTier->is_active,
            ] : null,
            'first_name' => $student->first_name,
            'last_name' => $student->last_name,
            'email' => $student->email,
            'whatsapp' => $student->whatsapp,
            'whatsapp_country_code' => \App\Support\CountryDirectory::splitPhoneNumber($student->whatsapp, $student->country)['country_code'],
            'whatsapp_number' => \App\Support\CountryDirectory::splitPhoneNumber($student->whatsapp, $student->country)['local_number'],
            'profile_photo' => $student->profile_photo,
            'profile_photo_url' => $this->protectedMediaUrl(
                'user',
                $student->id,
                'profile_photo',
                $student->profile_photo,
                versionSeed: $student->updated_at,
            ),
            'instagram' => $student->instagram,
            'country' => $student->country,
            'birth_date' => optional($student->birth_date)->toDateString(),
            'gender' => $student->gender,
            'practicing_yoga_for' => \App\Support\StudentProfileValue::normalizePracticingYogaFor($student->practicing_yoga_for),
            'yoga_sequence_experience' => \App\Support\StudentProfileValue::normalizeYogaSequenceExperience($student->yoga_sequence_experience),
            'hours_per_week' => \App\Support\StudentProfileValue::normalizeHoursPerWeek($student->hours_per_week),
            'current_fitness_level' => $student->current_fitness_level,
            'flexibility_rating' => $student->flexibility_rating,
            'motivation' => $student->motivation,
            'why_yogafx' => $student->why_yogafx,
            'how_did_you_find_us' => \App\Support\StudentProfileValue::normalizeHowDidYouFindUs($student->how_did_you_find_us),
            'profile_is_complete' => $student->hasCompletedStudentProfile(),
            'access_time_summary' => $this->sessionTrackingService->summaryForUser(
                $student,
            ),
        ];
    }

    private function sendEmail(User $student, string $subject, string $heading, array $bodyLines): void
    {
        abort_if(blank($student->email), 422, 'Student email is required to send this message.');

        Mail::to($student->email)->send(
            new StudentProgressActionMail($subject, $heading, $bodyLines),
        );
    }
}
