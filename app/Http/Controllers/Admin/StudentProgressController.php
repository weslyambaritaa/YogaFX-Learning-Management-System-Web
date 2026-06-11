<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Events\EmailNotifications\AssignmentApproved;
use App\Events\EmailNotifications\AssignmentRejected;
use App\Events\EmailNotifications\CertificateCreated;
use App\Mail\StudentProgressActionMail;
use App\Models\AccessTier;
use App\Models\AssignmentSubmission;
use App\Models\Certificate;
use App\Models\LessonProgress;
use App\Models\Module;
use App\Models\User;
use App\Services\StudentSessionTrackingService;
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
    public function __construct(
        private readonly StudentSessionTrackingService $sessionTrackingService,
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
                'assignmentSubmissions:id,user_id,assignment_type,assignment_video',
            ])
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
            'certificates' => $this->certificatesPayload($student),
            'certificateTypes' => collect(Certificate::TYPES)
                ->map(fn (string $label, string $value) => [
                    'value' => $value,
                    'label' => $label,
                ])
                ->values(),
            'certificateEligibility' => [
                'eligible' => $this->studentCanReceiveCertificates($student),
                'message' => $this->studentCanReceiveCertificates($student)
                    ? null
                    : 'This student is not eligible to receive a certificate based on the current access tier.',
            ],
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

        $assignmentSubmission->fill($data);
        $assignmentSubmission->graded_at = in_array(
            $assignmentSubmission->assignment_status,
            [AssignmentSubmission::STATUS_APPROVED, AssignmentSubmission::STATUS_REJECTED],
            true,
        ) ? now() : null;
        $assignmentSubmission->save();

        $emailPayload = [
            'user_name' => $student->name,
            'user_email' => $student->email,
            'assignment_type' => $assignmentSubmission->title(),
            'feedback' => $assignmentSubmission->assignment_feedback,
            'admin_email' => config('mail.from.address'),
        ];

        if ($assignmentSubmission->assignment_status === AssignmentSubmission::STATUS_APPROVED) {
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

        $assignmentSubmission->update([
            'assignment_video' => null,
        ]);

        return redirect()
            ->route('admin.student-progress.assignments.show', $student)
            ->with('status', 'student-progress-assignment-video-deleted');
    }

    public function generateCertificate(Request $request, User $student): RedirectResponse
    {
        $student = $this->resolveStudent($student);
        abort_unless($this->studentCanReceiveCertificates($student), 422);

        $data = $request->validate([
            'certificate_type' => ['required', 'in:'.implode(',', array_keys(Certificate::TYPES))],
        ]);

        $certificate = $this->createCertificate($student, $data['certificate_type']);

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
        abort_unless($this->studentCanReceiveCertificates($student), 422);

        $this->createCertificate($student, $certificate->certificate_type);

        return redirect()
            ->route('admin.student-progress.certificates.show', $student)
            ->with('status', 'student-progress-certificate-recreated');
    }

    public function sendGraduationEmail(User $student): RedirectResponse
    {
        $student = $this->resolveStudent($student);
        $certificates = Certificate::query()
            ->where('user_id', $student->id)
            ->latest('generated_at')
            ->get();

        abort_unless($certificates->isNotEmpty(), 422);

        $this->sendEmail(
            $student,
            'Your YogaFX graduation certificate update',
            'Graduation Certificate Update',
            [
                'Your certificate records are ready in YogaFX LMS.',
                'Available certificates: '.$certificates
                    ->map(fn (Certificate $certificate) => $certificate->typeLabel().' v'.$certificate->version)
                    ->join(', '),
            ],
        );

        $latestCertificate = $certificates->first();

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
        abort_unless(Storage::disk('local')->exists($certificate->file_path), 404);

        return Storage::disk('local')->download($certificate->file_path, $certificate->file_name);
    }

    public function destroyCertificate(User $student, Certificate $certificate): RedirectResponse
    {
        $student = $this->resolveStudent($student);
        abort_unless($certificate->user_id === $student->id, 404);

        if (Storage::disk('local')->exists($certificate->file_path)) {
            Storage::disk('local')->delete($certificate->file_path);
        }

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
                        'profile_photo' => $student->profile_photo,
                        'profile_initials' => $this->initialsFor($student),
                        'progress_percentage' => $this->progressPercentageForStudent($student, $modules, $tierId),
                        'registration_date' => optional($student->created_at)->format('Y-m-d'),
                        'assignment_status' => $this->assignmentStatusForStudent($student),
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
        $submittedTypes = $student->assignmentSubmissions
            ->filter(fn (AssignmentSubmission $submission) => filled($submission->assignment_video))
            ->pluck('assignment_type')
            ->map(fn (string $type) => str($type)->lower()->value());

        $hasLegacyGraduationSubmission = $submittedTypes->contains('graduation_video');
        $hasStandingSubmission = $submittedTypes->contains(fn (string $type) => str($type)->contains('standing'));
        $hasFloorSubmission = $submittedTypes->contains(fn (string $type) => str($type)->contains('floor'));

        return ($hasLegacyGraduationSubmission || ($hasStandingSubmission && $hasFloorSubmission))
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
            ->where('user_id', $student->id)
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (AssignmentSubmission $assignment) => [
                'id' => $assignment->id,
                'title' => $assignment->title(),
                'video' => $assignment->assignment_video,
                'status' => $assignment->assignment_status,
                'feedback' => $assignment->assignment_feedback,
                'submitted_at' => optional($assignment->submitted_at)->format('Y-m-d H:i'),
            ]);
    }

    private function certificatesPayload(User $student)
    {
        return Certificate::query()
            ->where('user_id', $student->id)
            ->latest('generated_at')
            ->latest('id')
            ->get()
            ->map(fn (Certificate $certificate) => [
                'id' => $certificate->id,
                'type' => $certificate->certificate_type,
                'type_label' => $certificate->typeLabel(),
                'version' => $certificate->version,
                'generated_at' => optional($certificate->generated_at)->format('Y-m-d H:i'),
                'download_url' => route('admin.student-progress.certificates.download', [
                    'student' => $student,
                    'certificate' => $certificate,
                ]),
            ]);
    }

    private function resolveStudent(User $student): User
    {
        abort_unless($student->isStudent(), 404);

        return $student->load('accessTier');
    }

    private function studentCanReceiveCertificates(User $student): bool
    {
        return $student->accessTier?->slug !== 'starter_kit'
            && $student->access_tier_id !== null;
    }

    private function createCertificate(User $student, string $certificateType): Certificate
    {
        $version = ((int) Certificate::withTrashed()
            ->where('user_id', $student->id)
            ->where('certificate_type', $certificateType)
            ->max('version')) + 1;

        $certificateLabel = Certificate::TYPES[$certificateType] ?? $certificateType;
        $timestamp = now();
        $safeStudentName = Str::slug($student->name ?: 'student');
        $safeType = Str::slug($certificateLabel);
        $fileName = "{$safeStudentName}-{$safeType}-v{$version}.html";
        $path = "certificates/{$student->id}/{$fileName}";

        $contents = view('certificates.template', [
            'student' => $student,
            'certificateLabel' => $certificateLabel,
            'generatedAt' => $timestamp,
            'version' => $version,
        ])->render();

        Storage::disk('local')->put($path, $contents);

        return Certificate::query()->create([
            'user_id' => $student->id,
            'certificate_type' => $certificateType,
            'file_path' => $path,
            'file_name' => $fileName,
            'version' => $version,
            'generated_by_user_id' => auth()->id(),
            'generated_at' => $timestamp,
        ]);
    }

    private function sendEmail(User $student, string $subject, string $heading, array $bodyLines): void
    {
        abort_if(blank($student->email), 422, 'Student email is required to send this message.');

        Mail::to($student->email)->send(
            new StudentProgressActionMail($subject, $heading, $bodyLines),
        );
    }
}
