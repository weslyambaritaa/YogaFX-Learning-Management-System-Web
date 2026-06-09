<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminStudentUpdateRequest;
use App\Models\AccessTier;
use App\Models\AssignmentSubmission;
use App\Models\Module;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class StudentController extends Controller
{
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
}
