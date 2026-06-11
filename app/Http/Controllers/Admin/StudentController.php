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
use App\Models\Lesson;
use App\Models\UserSession;
use App\Models\User;
use App\Services\StudentSessionTrackingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class StudentController extends Controller
{
    public function __construct(
        private readonly StudentSessionTrackingService $sessionTrackingService,
    ) {}

    public function studentsIndex(): Response
    {
        $students = User::query()
            ->with('accessTier:id,name,slug')
            ->where('role', User::ROLE_STUDENT)
            ->orderByDesc('created_at')
            ->get([
                'id',
                'name',
                'first_name',
                'last_name',
                'email',
                'is_active',
                'access_tier_id',
                'profile_photo',
                'created_at',
            ])
            ->map(fn (User $student, int $index) => [
                'id' => $student->id,
                'number' => $index + 1,
                'name' => $student->name ?: trim("{$student->first_name} {$student->last_name}"),
                'email' => $student->email,
                'profile_photo' => $student->profile_photo,
                'profile_initials' => $this->initialsFor($student),
                'access_tier_name' => $student->accessTier?->name ?? 'Not assigned',
                'is_active' => (bool) $student->is_active,
                'registration_date' => optional($student->created_at)->format('Y-m-d'),
            ]);

        return Inertia::render('Admin/Students/Index', [
            'students' => $students,
            'status' => session('status'),
        ]);
    }

    public function studentsEdit(User $student): Response
    {
        abort_unless($student->isStudent(), 404);

        return Inertia::render('Admin/Students/Edit', [
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

    public function studentsUpdate(AdminStudentUpdateRequest $request, User $student): RedirectResponse
    {
        abort_unless($student->isStudent(), 404);

        $student->fill($request->validated());
        $student->syncDisplayName();

        if ($student->isDirty('email')) {
            $student->email_verified_at = null;
        }

        $student->save();

        return redirect()
            ->route('admin.students.edit', $student)
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
            ->route('admin.students.edit', $student)
            ->with('status', 'student-status-updated');
    }

    public function resetProgress(User $student): RedirectResponse
    {
        abort_unless($student->isStudent(), 404);

        DB::transaction(function () use ($student) {
            $this->resetAllLearningProgress($student);
        });

        return redirect()
            ->route('admin.students.edit', $student)
            ->with('status', 'student-learning-progress-reset');
    }

    public function resetProgressScope(User $student, string $scope): RedirectResponse
    {
        abort_unless($student->isStudent(), 404);

        abort_unless(in_array($scope, ['video', 'assessment', 'lesson', 'module'], true), 404);

        DB::transaction(function () use ($student, $scope) {
            match ($scope) {
                'video' => $this->resetVideoProgress($student),
                'assessment' => $this->resetAssessmentProgress($student),
                'lesson' => $this->resetLessonProgress($student),
                'module' => $this->resetModuleProgress($student),
            };
        });

        return redirect()
            ->route('admin.students.edit', $student)
            ->with('status', 'student-progress-reset-'.$scope);
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
            ->route('admin.students.index')
            ->with('status', 'student-account-deleted');
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

    private function resetAllLearningProgress(User $student): void
    {
        $this->resetAssessmentProgress($student);
        $this->resetLessonProgress($student);
    }

    private function resetVideoProgress(User $student): void
    {
        LessonProgress::query()
            ->where('user_id', $student->id)
            ->update([
                'watch_progress' => 0,
                'video_completed_at' => null,
                'is_done' => false,
                'completed_at' => null,
            ]);
    }

    private function resetAssessmentProgress(User $student): void
    {
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

        $lessonIdsWithAssessment = Lesson::query()
            ->whereNotNull('assessment_id')
            ->pluck('id');

        if ($lessonIdsWithAssessment->isNotEmpty()) {
            LessonProgress::query()
                ->where('user_id', $student->id)
                ->whereIn('lesson_id', $lessonIdsWithAssessment)
                ->update([
                    'is_done' => false,
                    'completed_at' => null,
                ]);
        }
    }

    private function resetLessonProgress(User $student): void
    {
        LessonProgress::query()->where('user_id', $student->id)->delete();
    }

    private function resetModuleProgress(User $student): void
    {
        $this->resetAssessmentProgress($student);
        $this->resetLessonProgress($student);
    }
}
