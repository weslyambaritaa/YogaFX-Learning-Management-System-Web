<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminStudentUpdateRequest;
use App\Models\AccessTier;
use App\Models\AssessmentAnswer;
use App\Models\AssessmentAttempt;
use App\Models\AssessmentProgress;
use App\Models\AssignmentSubmission;
use App\Models\Certificate;
use App\Models\LessonProgress;
use App\Models\UserSession;
use App\Models\Module;
use App\Models\User;
use App\Services\StudentSessionTrackingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class StudentController extends Controller
{
    public function __construct(
        private readonly StudentSessionTrackingService $sessionTrackingService,
    ) {}

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
            ->orderByDesc('created_at')
            ->get([
                'id',
                'name',
                'first_name',
                'last_name',
                'email',
                'is_active',
                'country',
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

        $studentsByTier = $this->buildStudentsByTierPayload($students, $tiers, $modules);

        return Inertia::render('Admin/StudentProgress/Directory', [
            'tierSections' => $studentsByTier,
            'status' => session('status'),
        ]);
    }

    public function edit(User $student): Response
    {
        abort_unless($student->isStudent(), 404);

        return Inertia::render('Admin/StudentProgress/Edit', [
            'student' => [
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
                'preferred_certificate_picture' => $student->preferred_certificate_picture,
                'profile_photo' => $student->profile_photo,
                'instagram' => $student->instagram,
                'country' => $student->country,
                'birth_date' => optional($student->birth_date)->toDateString(),
                'gender' => $student->gender,
                'practicing_yoga_for' => $student->practicing_yoga_for,
                'yoga_sequence_experience' => $student->yoga_sequence_experience,
                'hours_per_week' => $student->hours_per_week,
                'current_fitness_level' => $student->current_fitness_level,
                'flexibility_rating' => $student->flexibility_rating,
                'motivation' => $student->motivation,
                'why_yogafx' => $student->why_yogafx,
                'how_did_you_find_us' => $student->how_did_you_find_us,
                'profile_is_complete' => $student->hasCompletedStudentProfile(),
                'access_time_summary' => $this->sessionTrackingService->summaryForUser(
                    $student,
                ),
            ],
            'accessTiers' => AccessTier::query()
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->get()
                ->map(fn (AccessTier $accessTier) => [
                    'id' => $accessTier->id,
                    'name' => $accessTier->name,
                    'slug' => $accessTier->slug,
                    'is_active' => $accessTier->is_active,
                ]),
            'status' => session('status'),
        ]);
    }

    public function update(AdminStudentUpdateRequest $request, User $student): RedirectResponse
    {
        abort_unless($student->isStudent(), 404);

        $student->fill($request->validated());
        $student->syncDisplayName();

        if ($student->isDirty('email')) {
            $student->email_verified_at = null;
        }

        $student->save();

        return redirect()
            ->route('admin.student-progress.index')
            ->with('status', 'student-profile-updated');
    }

    public function updateStatus(User $student): RedirectResponse
    {
        abort_unless($student->isStudent(), 404);

        $validated = request()->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $student->forceFill([
            'is_active' => (bool) $validated['is_active'],
        ])->save();

        return redirect()
            ->route('admin.student-progress.students.edit', $student)
            ->with('status', 'student-status-updated');
    }

    public function resetProgress(User $student): RedirectResponse
    {
        abort_unless($student->isStudent(), 404);

        $certificateFiles = Certificate::query()
            ->where('user_id', $student->id)
            ->pluck('file_path')
            ->filter()
            ->values();

        DB::transaction(function () use ($student) {
            $attemptIds = AssessmentAttempt::query()
                ->where('user_id', $student->id)
                ->pluck('id');

            if ($attemptIds->isNotEmpty()) {
                AssessmentAnswer::query()
                    ->whereIn('assessment_attempt_id', $attemptIds)
                    ->delete();
            }

            AssessmentAttempt::query()->where('user_id', $student->id)->delete();
            AssessmentProgress::query()->where('user_id', $student->id)->delete();
            LessonProgress::query()->where('user_id', $student->id)->delete();
            AssignmentSubmission::query()->where('user_id', $student->id)->delete();
            Certificate::query()->where('user_id', $student->id)->delete();
        });

        $certificateFiles->each(function (string $path) {
            if (Storage::disk('local')->exists($path)) {
                Storage::disk('local')->delete($path);
            }
        });

        return redirect()
            ->route('admin.student-progress.students.edit', $student)
            ->with('status', 'student-progress-reset');
    }

    public function destroy(User $student): RedirectResponse
    {
        abort_unless($student->isStudent(), 404);

        $certificateFiles = Certificate::withTrashed()
            ->where('user_id', $student->id)
            ->pluck('file_path')
            ->filter()
            ->values();

        DB::transaction(function () use ($student) {
            $attemptIds = AssessmentAttempt::query()
                ->where('user_id', $student->id)
                ->pluck('id');

            if ($attemptIds->isNotEmpty()) {
                AssessmentAnswer::query()
                    ->whereIn('assessment_attempt_id', $attemptIds)
                    ->delete();
            }

            AssessmentAttempt::query()->where('user_id', $student->id)->delete();
            AssessmentProgress::query()->where('user_id', $student->id)->delete();
            LessonProgress::query()->where('user_id', $student->id)->delete();
            AssignmentSubmission::query()->where('user_id', $student->id)->delete();
            Certificate::withTrashed()->where('user_id', $student->id)->forceDelete();
            UserSession::query()->where('user_id', $student->id)->delete();
            DB::table('sessions')->where('user_id', $student->id)->delete();
            $student->delete();
        });

        $certificateFiles->each(function (string $path) {
            if (Storage::disk('local')->exists($path)) {
                Storage::disk('local')->delete($path);
            }
        });

        return redirect()
            ->route('admin.student-progress.index')
            ->with('status', 'student-account-deleted');
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
            'unassigned' => 'Unassigned',
        ]);

        return $sectionDefinitions->map(function (string $label, string $slug) use ($students, $tiers, $modules) {
            $tier = $tiers->firstWhere('slug', $slug);
            $tierId = $tier?->id;

            return [
                'slug' => $slug,
                'label' => $label,
                'students' => $students
                    ->filter(fn (User $student) => $slug === 'unassigned'
                        ? $student->accessTier === null
                        : $student->accessTier?->slug === $slug)
                    ->values()
                    ->map(fn (User $student, int $index) => [
                        'id' => $student->id,
                        'number' => $index + 1,
                        'name' => $student->name ?: trim("{$student->first_name} {$student->last_name}"),
                        'email' => $student->email,
                        'profile_photo' => $student->profile_photo,
                        'profile_initials' => $this->initialsFor($student),
                        'access_tier_name' => $student->accessTier?->name ?? 'Not assigned',
                        'is_active' => $student->isStudentAccountActive(),
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
}
