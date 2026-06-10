<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Concerns\BuildsProtectedMediaUrls;
use App\Http\Controllers\Controller;
use App\Models\AccessTier;
use App\Models\AssignmentSubmission;
use App\Models\Certificate;
use App\Models\Ebook;
use App\Models\LessonProgress;
use App\Models\Module;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    use BuildsProtectedMediaUrls;

    public function index(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if ($user && ! $user->hasCompletedStudentProfile()) {
            return redirect()->route('profile.edit');
        }

        $displayName = trim((string) ($user?->first_name ?: $user?->name ?: 'Student'));
        $tier = $user?->accessTier;
        $availableModules = $this->availableModulesForStudent($user?->access_tier_id);
        $continueLearning = $this->buildContinueLearning($request, $availableModules);
        $progressSummary = $this->buildProgressSummary($request, $availableModules);
        $nextStep = $this->buildNextStep($request, $availableModules, $continueLearning);
        $sequentialAwareness = $this->buildSequentialAwareness($request, $availableModules, $continueLearning);
        $availableModulesSection = $this->buildAvailableModulesSection($request, $availableModules);
        $assignmentMilestone = $this->buildAssignmentMilestone($request, $continueLearning);
        $certificateMilestone = $this->buildCertificateMilestone($request, $progressSummary, $continueLearning);
        $ebookResourcesSection = $this->buildEbookResourcesSection($request);
        $homeExperience = $this->buildHomeExperience(
            $request,
            $continueLearning,
            $progressSummary,
            $availableModulesSection,
            $certificateMilestone,
            $ebookResourcesSection,
        );

        return Inertia::render('Student/Home', [
            'homeStage' => 11,
            'studentContext' => [
                'display_name' => $displayName !== '' ? $displayName : 'Student',
                'full_name' => $user?->name ?: $displayName,
                'email' => $user?->email,
                'profile_is_complete' => $user?->hasCompletedStudentProfile() ?? false,
                'access_tier' => $tier ? [
                    'id' => $tier->id,
                    'name' => $tier->name,
                    'slug' => $tier->slug,
                    'is_active' => $tier->is_active,
                ] : null,
            ],
            'continueLearning' => $continueLearning,
            'progressSummary' => $progressSummary,
            'nextStep' => $nextStep,
            'sequentialAwareness' => $sequentialAwareness,
            'availableModulesSection' => $availableModulesSection,
            'assignmentMilestone' => $assignmentMilestone,
            'certificateMilestone' => $certificateMilestone,
            'ebookResourcesSection' => $ebookResourcesSection,
            'homeExperience' => $homeExperience,
        ]);
    }

    public function downloadCertificate(Request $request, Certificate $certificate)
    {
        $user = $request->user();

        abort_unless($user?->isStudent() && $certificate->user_id === $user->id, 404);
        abort_unless(Storage::disk('local')->exists($certificate->file_path), 404);

        return Storage::disk('local')->download($certificate->file_path, $certificate->file_name);
    }

    protected function availableModulesForStudent(?int $accessTierId): Collection
    {
        if (! $accessTierId) {
            return collect();
        }

        return Module::query()
            ->whereHas('accessTiers', fn ($query) => $query->where('access_tiers.id', $accessTierId))
            ->with([
                'lessons' => fn ($query) => $query
                    ->whereHas('accessTiers', fn ($lessonQuery) => $lessonQuery->where('access_tiers.id', $accessTierId))
                    ->orderBy('sort_order')
                    ->orderBy('title'),
            ])
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get();
    }

    protected function buildContinueLearning(Request $request, Collection $availableModules): array
    {
        $user = $request->user();
        $accessTierId = $user?->access_tier_id;

        if (! $user || ! $accessTierId) {
            return [
                'state' => 'empty',
                'eyebrow' => 'Continue Watching',
                'title' => 'Your learning journey will appear here.',
                'description' => 'Assign an access tier first so Home can safely surface the right lesson and module.',
                'progress_percentage' => 0,
                'cta_label' => 'Open Profile',
                'cta_url' => route('profile.edit'),
                'module' => null,
                'lesson' => null,
                'thumbnail_url' => null,
                'status' => 'Awaiting access tier',
            ];
        }

        $latestProgress = LessonProgress::query()
            ->where('user_id', $user->id)
            ->whereHas('lesson', function ($query) use ($accessTierId) {
                $query
                    ->whereHas('accessTiers', fn ($lessonQuery) => $lessonQuery->where('access_tiers.id', $accessTierId))
                    ->whereHas('module', fn ($moduleQuery) => $moduleQuery->whereHas('accessTiers', fn ($tierQuery) => $tierQuery->where('access_tiers.id', $accessTierId)));
            })
            ->with(['lesson.module'])
            ->latest('updated_at')
            ->latest('id')
            ->first();

        if ($latestProgress?->lesson && $latestProgress->lesson->module) {
            $lesson = $latestProgress->lesson;
            $module = $lesson->module;
            $watchProgress = (int) round((float) $latestProgress->watch_progress);
            $progressPercentage = $latestProgress->is_done
                ? 100
                : max(0, min(100, $watchProgress));

            return [
                'state' => 'resume',
                'eyebrow' => 'Continue Watching',
                'title' => $lesson->title,
                'description' => $latestProgress->is_done
                    ? "You last opened {$lesson->title} in {$module->title}. You can jump back in from where your learning momentum paused."
                    : "Return to {$lesson->title} in {$module->title} and keep your YogaFX learning flow moving.",
                'progress_percentage' => $progressPercentage,
                'cta_label' => $latestProgress->is_done ? 'Open Lesson Again' : 'Continue Lesson',
                'cta_url' => route('lessons.show', $lesson),
                'module' => [
                    'title' => $module->title,
                    'url_slug' => $module->url_slug,
                    'url' => route('modules.show', $module->url_slug),
                ],
                'lesson' => [
                    'id' => $lesson->id,
                    'title' => $lesson->title,
                    'sort_order' => $lesson->sort_order,
                ],
                'thumbnail_url' => $this->protectedMediaUrl(
                    'lesson',
                    $lesson->id,
                    'thumbnail',
                    $lesson->thumbnail,
                    versionSeed: $lesson->updated_at,
                ) ?: $this->protectedMediaUrl(
                    'module',
                    $module->id,
                    'thumbnail',
                    $module->thumbnail,
                    versionSeed: $module->updated_at,
                ),
                'status' => $latestProgress->is_done
                    ? 'Last lesson completed'
                    : ($progressPercentage > 0 ? "{$progressPercentage}% complete" : 'In progress'),
            ];
        }

        $startingModule = $availableModules->first(fn (Module $module) => $module->lessons->isNotEmpty());
        $startingLesson = $startingModule?->lessons->first();

        if ($startingModule && $startingLesson) {
            return [
                'state' => 'start',
                'eyebrow' => 'Start Watching',
                'title' => $startingLesson->title,
                'description' => "Begin with {$startingLesson->title} from {$startingModule->title} and start building your YogaFX learning momentum.",
                'progress_percentage' => 0,
                'cta_label' => 'Start First Lesson',
                'cta_url' => route('lessons.show', $startingLesson),
                'module' => [
                    'title' => $startingModule->title,
                    'url_slug' => $startingModule->url_slug,
                    'url' => route('modules.show', $startingModule->url_slug),
                ],
                'lesson' => [
                    'id' => $startingLesson->id,
                    'title' => $startingLesson->title,
                    'sort_order' => $startingLesson->sort_order,
                ],
                'thumbnail_url' => $this->protectedMediaUrl(
                    'lesson',
                    $startingLesson->id,
                    'thumbnail',
                    $startingLesson->thumbnail,
                    versionSeed: $startingLesson->updated_at,
                ) ?: $this->protectedMediaUrl(
                    'module',
                    $startingModule->id,
                    'thumbnail',
                    $startingModule->thumbnail,
                    versionSeed: $startingModule->updated_at,
                ),
                'status' => 'Ready to start',
            ];
        }

        return [
            'state' => 'empty',
            'eyebrow' => 'Continue Watching',
            'title' => 'No lesson is available to continue yet.',
            'description' => 'Home is ready, but your module and lesson catalog still needs at least one accessible lesson before Continue Learning can appear.',
            'progress_percentage' => 0,
            'cta_label' => 'Browse Modules',
            'cta_url' => route('modules.index'),
            'module' => null,
            'lesson' => null,
            'thumbnail_url' => null,
            'status' => 'No accessible lesson yet',
        ];
    }

    protected function buildProgressSummary(Request $request, Collection $availableModules): array
    {
        $user = $request->user();
        $lessonCollection = $availableModules
            ->filter(fn (Module $module) => $module->lessons->isNotEmpty())
            ->flatMap(fn (Module $module) => $module->lessons)
            ->values();

        $moduleCollection = $availableModules
            ->filter(fn (Module $module) => $module->lessons->isNotEmpty())
            ->values();

        if (! $user || ! $user->access_tier_id || $lessonCollection->isEmpty()) {
            return [
                'state' => 'empty',
                'eyebrow' => 'Learning Progress',
                'title' => 'Your progress summary will appear here.',
                'description' => 'Once your YogaFX lesson access is ready, Home will highlight how many lessons and modules you have completed.',
                'overall_progress_percentage' => 0,
                'modules_completed' => 0,
                'modules_total' => $moduleCollection->count(),
                'lessons_completed' => 0,
                'lessons_total' => $lessonCollection->count(),
                'status' => 'Progress not available yet',
            ];
        }

        $completedLessonIds = LessonProgress::query()
            ->where('user_id', $user->id)
            ->whereIn('lesson_id', $lessonCollection->pluck('id'))
            ->where('is_done', true)
            ->pluck('lesson_id')
            ->map(fn ($lessonId) => (int) $lessonId)
            ->unique()
            ->values();

        $completedLessonLookup = $completedLessonIds->flip();
        $lessonsTotal = $lessonCollection->count();
        $lessonsCompleted = $completedLessonIds->count();
        $modulesTotal = $moduleCollection->count();
        $modulesCompleted = $moduleCollection
            ->filter(fn (Module $module) => $module->lessons->every(
                fn ($lesson) => $completedLessonLookup->has((int) $lesson->id)
            ))
            ->count();

        $overallProgress = $lessonsTotal > 0
            ? (int) round(($lessonsCompleted / $lessonsTotal) * 100)
            : 0;

        $description = $lessonsCompleted === 0
            ? 'You are at the beginning of your YogaFX journey. Start your first lesson to turn this space into a visible learning streak.'
            : ($overallProgress === 100
                ? 'Every accessible lesson in your current tier is complete. Your learning journey has reached a major milestone.'
                : "You have completed {$lessonsCompleted} of {$lessonsTotal} accessible lessons and {$modulesCompleted} of {$modulesTotal} modules so far.");

        return [
            'state' => $lessonsCompleted === 0 ? 'empty' : 'ready',
            'eyebrow' => 'Learning Progress',
            'title' => $overallProgress === 100
                ? 'You have completed your current YogaFX learning path'
                : 'Your learning momentum is building',
            'description' => $description,
            'overall_progress_percentage' => $overallProgress,
            'modules_completed' => $modulesCompleted,
            'modules_total' => $modulesTotal,
            'lessons_completed' => $lessonsCompleted,
            'lessons_total' => $lessonsTotal,
            'status' => $overallProgress === 100
                ? 'Current tier completed'
                : ($lessonsCompleted === 0 ? 'No completed lesson yet' : 'Progress updated from completed lessons'),
        ];
    }

    protected function buildNextStep(Request $request, Collection $availableModules, array $continueLearning): array
    {
        $user = $request->user();
        $accessTierId = $user?->access_tier_id;

        if (! $user || ! $accessTierId) {
            return [
                'state' => 'empty',
                'kind' => 'profile',
                'eyebrow' => 'Recommended Next Step',
                'title' => 'Complete your student setup first',
                'description' => 'Your Home is ready, but YogaFX still needs an active access tier before it can recommend the right learning action.',
                'status' => 'Awaiting access tier',
                'cta_label' => 'Open Profile',
                'cta_url' => route('profile.edit'),
                'module' => null,
                'lesson' => null,
            ];
        }

        if (($continueLearning['state'] ?? null) === 'resume'
            && ($continueLearning['progress_percentage'] ?? 0) < 100
            && filled($continueLearning['lesson']['id'] ?? null)) {
            return [
                'state' => 'ready',
                'kind' => 'continue_lesson',
                'eyebrow' => 'Recommended Next Step',
                'title' => 'Continue your current lesson',
                'description' => 'The strongest next move is to return to the lesson you already started and keep the momentum going without switching tracks.',
                'status' => $continueLearning['status'] ?? 'In progress',
                'cta_label' => 'Continue Lesson',
                'cta_url' => $continueLearning['cta_url'] ?? route('modules.index'),
                'module' => $continueLearning['module'] ?? null,
                'lesson' => $continueLearning['lesson'] ?? null,
            ];
        }

        if (($continueLearning['state'] ?? null) === 'start'
            && filled($continueLearning['lesson']['id'] ?? null)) {
            return [
                'state' => 'ready',
                'kind' => 'start_lesson',
                'eyebrow' => 'Recommended Next Step',
                'title' => 'Start your first lesson now',
                'description' => 'You already have a safe starting point. Begin with the first accessible YogaFX lesson and let Home build your learning rhythm from there.',
                'status' => $continueLearning['status'] ?? 'Ready to start',
                'cta_label' => $continueLearning['cta_label'] ?? 'Start First Lesson',
                'cta_url' => $continueLearning['cta_url'] ?? route('modules.index'),
                'module' => $continueLearning['module'] ?? null,
                'lesson' => $continueLearning['lesson'] ?? null,
            ];
        }

        $orderedLessonEntries = $availableModules
            ->filter(fn (Module $module) => $module->lessons->isNotEmpty())
            ->flatMap(fn (Module $module) => $module->lessons->map(fn ($lesson) => [
                'module' => $module,
                'lesson' => $lesson,
            ]))
            ->values();

        if ($orderedLessonEntries->isEmpty()) {
            return [
                'state' => 'empty',
                'kind' => 'explore_modules',
                'eyebrow' => 'Recommended Next Step',
                'title' => 'Explore your available modules',
                'description' => 'No accessible lesson is ready yet, so the safest next step is to open your YogaFX catalog and check what is already available in your tier.',
                'status' => 'No accessible lesson yet',
                'cta_label' => 'Browse Modules',
                'cta_url' => route('modules.index'),
                'module' => null,
                'lesson' => null,
            ];
        }

        $lessonIds = $orderedLessonEntries->pluck('lesson.id');
        $progressMap = LessonProgress::query()
            ->where('user_id', $user->id)
            ->whereIn('lesson_id', $lessonIds)
            ->get()
            ->keyBy('lesson_id');

        $latestCompletedProgress = LessonProgress::query()
            ->where('user_id', $user->id)
            ->whereIn('lesson_id', $lessonIds)
            ->where('is_done', true)
            ->latest('updated_at')
            ->latest('id')
            ->first();

        if ($latestCompletedProgress) {
            $completedIndex = $orderedLessonEntries->search(
                fn (array $entry) => (int) $entry['lesson']->id === (int) $latestCompletedProgress->lesson_id
            );

            if ($completedIndex !== false) {
                $nextEntry = $orderedLessonEntries
                    ->slice($completedIndex + 1)
                    ->first(fn (array $entry) => ! (bool) optional($progressMap->get($entry['lesson']->id))->is_done);

                if ($nextEntry) {
                    return [
                        'state' => 'ready',
                        'kind' => 'next_lesson',
                        'eyebrow' => 'Recommended Next Step',
                        'title' => 'Move into the next available lesson',
                        'description' => "You finished the previous lesson, so the cleanest next step is {$nextEntry['lesson']->title} from {$nextEntry['module']->title}.",
                        'status' => 'Ready now',
                        'cta_label' => 'Open Next Lesson',
                        'cta_url' => route('lessons.show', $nextEntry['lesson']),
                        'module' => [
                            'title' => $nextEntry['module']->title,
                            'url_slug' => $nextEntry['module']->url_slug,
                            'url' => route('modules.show', $nextEntry['module']->url_slug),
                        ],
                        'lesson' => [
                            'id' => $nextEntry['lesson']->id,
                            'title' => $nextEntry['lesson']->title,
                            'sort_order' => $nextEntry['lesson']->sort_order,
                        ],
                    ];
                }
            }
        }

        $firstIncompleteEntry = $orderedLessonEntries
            ->first(fn (array $entry) => ! (bool) optional($progressMap->get($entry['lesson']->id))->is_done);

        if ($firstIncompleteEntry) {
            return [
                'state' => 'ready',
                'kind' => 'available_lesson',
                'eyebrow' => 'Recommended Next Step',
                'title' => 'Return to the next available lesson',
                'description' => "Home found {$firstIncompleteEntry['lesson']->title} as the next unfinished lesson available in your current YogaFX path.",
                'status' => 'Available now',
                'cta_label' => 'Open Lesson',
                'cta_url' => route('lessons.show', $firstIncompleteEntry['lesson']),
                'module' => [
                    'title' => $firstIncompleteEntry['module']->title,
                    'url_slug' => $firstIncompleteEntry['module']->url_slug,
                    'url' => route('modules.show', $firstIncompleteEntry['module']->url_slug),
                ],
                'lesson' => [
                    'id' => $firstIncompleteEntry['lesson']->id,
                    'title' => $firstIncompleteEntry['lesson']->title,
                    'sort_order' => $firstIncompleteEntry['lesson']->sort_order,
                ],
            ];
        }

        return [
            'state' => 'complete',
            'kind' => 'explore_modules',
            'eyebrow' => 'Recommended Next Step',
            'title' => 'You have finished every accessible lesson for now',
            'description' => 'Your current learning path is complete. Revisit your module catalog to review, refresh, and stay connected to the YogaFX journey.',
            'status' => 'Current path completed',
            'cta_label' => 'Browse Modules',
            'cta_url' => route('modules.index'),
            'module' => null,
            'lesson' => null,
        ];
    }

    protected function buildAvailableModulesSection(Request $request, Collection $availableModules): array
    {
        $user = $request->user();
        $moduleCollection = $availableModules
            ->filter(fn (Module $module) => $module->lessons->isNotEmpty())
            ->values();

        if (! $user || ! $user->access_tier_id) {
            return [
                'state' => 'empty',
                'eyebrow' => 'Available Modules',
                'title' => 'Your module catalog will appear here.',
                'description' => 'Assign an access tier first so Home can safely reveal the modules that belong to this student journey.',
                'items' => [],
                'summary' => [
                    'total' => 0,
                    'completed' => 0,
                    'active' => 0,
                    'available' => 0,
                ],
            ];
        }

        if ($moduleCollection->isEmpty()) {
            return [
                'state' => 'empty',
                'eyebrow' => 'Available Modules',
                'title' => 'No module is available in this tier yet.',
                'description' => 'Home is ready to show a premium module catalog, but there are no accessible modules with lessons for the current student tier yet.',
                'items' => [],
                'summary' => [
                    'total' => 0,
                    'completed' => 0,
                    'active' => 0,
                    'available' => 0,
                ],
            ];
        }

        $lessonProgressMap = $this->lessonProgressMap(
            $user->id,
            $moduleCollection->flatMap(fn (Module $module) => $module->lessons->pluck('id')),
        );
        $activeLessonId = $this->latestProgressLessonId($user->id, $lessonProgressMap);

        $items = $moduleCollection->map(function (Module $module) use ($activeLessonId, $lessonProgressMap) {
            $totalLessons = $module->lessons->count();
            $completedLessons = $module->lessons
                ->filter(fn ($lesson) => (bool) optional($lessonProgressMap->get($lesson->id))->is_done)
                ->count();
            $isActive = $module->lessons->contains(fn ($lesson) => $lesson->id === $activeLessonId);
            $status = $totalLessons > 0 && $completedLessons === $totalLessons
                ? 'completed'
                : ($isActive ? 'active' : 'available');
            $statusLabel = match ($status) {
                'completed' => 'Completed',
                'active' => 'Continue',
                default => 'Available',
            };

            return [
                'id' => $module->id,
                'title' => $module->title,
                'url_slug' => $module->url_slug,
                'sort_order' => $module->sort_order,
                'lesson_count' => $totalLessons,
                'completed_lessons' => $completedLessons,
                'progress_percentage' => $totalLessons > 0
                    ? (int) round(($completedLessons / $totalLessons) * 100)
                    : 0,
                'status' => $status,
                'status_label' => $statusLabel,
                'cta_label' => match ($status) {
                    'completed' => 'Review Module',
                    'active' => 'Continue Module',
                    default => 'Open Module',
                },
                'cta_url' => route('modules.show', $module->url_slug),
                'thumbnail_url' => $this->protectedMediaUrl(
                    'module',
                    $module->id,
                    'thumbnail',
                    $module->thumbnail,
                    versionSeed: $module->updated_at,
                ),
            ];
        })->values();

        return [
            'state' => 'ready',
            'eyebrow' => 'Available Modules',
            'title' => 'Browse the modules available in your YogaFX path',
            'description' => 'This section only shows content unlocked by the current tier, so the catalog stays relevant and calm instead of overwhelming.',
            'items' => $items,
            'summary' => [
                'total' => $items->count(),
                'completed' => $items->where('status', 'completed')->count(),
                'active' => $items->where('status', 'active')->count(),
                'available' => $items->where('status', 'available')->count(),
            ],
        ];
    }

    protected function buildSequentialAwareness(Request $request, Collection $availableModules, array $continueLearning): array
    {
        $user = $request->user();
        $moduleCollection = $availableModules
            ->filter(fn (Module $module) => $module->lessons->isNotEmpty())
            ->values();

        if (! $user || ! $user->access_tier_id || $moduleCollection->isEmpty()) {
            return [
                'state' => 'empty',
                'eyebrow' => 'Learning Sequence',
                'title' => 'Sequence awareness will appear here.',
                'description' => 'Once accessible lessons are ready, Home will explain which lesson should come first and what the next step in the sequence looks like.',
                'status' => 'Awaiting lesson access',
                'current_lesson' => null,
                'next_lesson' => null,
                'sequence_rule' => [
                    'label' => 'Sequence guidance is not available yet.',
                    'detail' => 'No accessible lesson has been found for this student tier.',
                ],
                'supporting_rules' => [],
            ];
        }

        $orderedLessonEntries = $moduleCollection
            ->flatMap(fn (Module $module) => $module->lessons->map(fn ($lesson) => [
                'module' => $module,
                'lesson' => $lesson,
            ]))
            ->values();

        $progressMap = $this->lessonProgressMap(
            $user->id,
            $orderedLessonEntries->pluck('lesson.id'),
        );

        $currentEntry = null;
        if (filled($continueLearning['lesson']['id'] ?? null)) {
            $currentEntry = $orderedLessonEntries->first(
                fn (array $entry) => (int) $entry['lesson']->id === (int) $continueLearning['lesson']['id']
            );
        }

        if (! $currentEntry) {
            $latestProgressLessonId = $this->latestProgressLessonId($user->id, $progressMap);
            $currentEntry = $orderedLessonEntries->first(
                fn (array $entry) => (int) $entry['lesson']->id === (int) $latestProgressLessonId
            ) ?: $orderedLessonEntries->first();
        }

        if (! $currentEntry) {
            return [
                'state' => 'empty',
                'eyebrow' => 'Learning Sequence',
                'title' => 'Sequence awareness will appear here.',
                'description' => 'Home could not resolve the current lesson sequence yet.',
                'status' => 'No sequence data',
                'current_lesson' => null,
                'next_lesson' => null,
                'sequence_rule' => [
                    'label' => 'No sequence data available.',
                    'detail' => 'The current lesson ordering is not ready to be shown.',
                ],
                'supporting_rules' => [],
            ];
        }

        $currentIndex = $orderedLessonEntries->search(
            fn (array $entry) => (int) $entry['lesson']->id === (int) $currentEntry['lesson']->id
        );
        $nextEntry = $currentIndex !== false
            ? $orderedLessonEntries->slice($currentIndex + 1)->first()
            : null;
        $currentProgress = $progressMap->get($currentEntry['lesson']->id);
        $watchProgress = (int) round((float) ($currentProgress?->watch_progress ?? 0));
        $isDone = (bool) ($currentProgress?->is_done ?? false);
        $hasWorkbook = filled($currentEntry['lesson']->workbook ?? null);
        $hasAssessment = filled($currentEntry['lesson']->assessment_id ?? null);

        $sequenceRule = $isDone
            ? [
                'label' => $nextEntry
                    ? 'Your next lesson in sequence is ready.'
                    : 'You have reached the end of the current accessible sequence.',
                'detail' => $nextEntry
                    ? "You completed {$currentEntry['lesson']->title}. The next lesson in order is {$nextEntry['lesson']->title}."
                    : "Every accessible lesson after {$currentEntry['lesson']->title} has already been reached in your current YogaFX path.",
            ]
            : [
                'label' => $nextEntry
                    ? "Finish {$currentEntry['lesson']->title} before moving forward in sequence."
                    : "Complete {$currentEntry['lesson']->title} to close the current sequence cleanly.",
                'detail' => $nextEntry
                    ? "{$nextEntry['lesson']->title} is the next lesson in order, so Home keeps the sequence focused on the lesson you are in right now."
                    : 'This lesson currently acts as the last accessible stop in your sequence.',
            ];

        $supportingRules = collect([
            [
                'label' => $hasWorkbook
                    ? 'Workbook is attached to this lesson.'
                    : 'No workbook is attached to this lesson.',
                'detail' => $hasWorkbook
                    ? 'The workbook is available on the lesson page. Workbook gating is not active yet, so Home only surfaces this as guidance.'
                    : 'There is no workbook dependency to surface for this lesson right now.',
            ],
            [
                'label' => $hasAssessment
                    ? 'Assessment relation is attached to this lesson.'
                    : 'No assessment relation is attached to this lesson.',
                'detail' => $hasAssessment
                    ? ($watchProgress >= 95
                        ? 'The 95% watch target has been reached. Assessment player flow is still not active on student side, so Home keeps this as readiness guidance only.'
                        : "The future readiness target remains 95% watch progress. Current progress is {$watchProgress}%, and assessment player flow is still not active on student side.")
                    : 'There is no assessment dependency to surface for this lesson right now.',
            ],
        ])->values();

        return [
            'state' => $isDone ? ($nextEntry ? 'ready' : 'complete') : 'in_progress',
            'eyebrow' => 'Learning Sequence',
            'title' => $isDone
                ? ($nextEntry
                    ? 'Your next lesson in sequence is already visible'
                    : 'You have completed the current accessible lesson sequence')
                : 'Stay with the intended lesson order before moving ahead',
            'description' => $isDone
                ? ($nextEntry
                    ? "Home now points to {$nextEntry['lesson']->title} as the clean next step in the YogaFX sequence."
                    : 'The current accessible path is complete, so Home can now shift back toward review and discovery.')
                : "Home is tracking {$currentEntry['lesson']->title} as the lesson that should be finished before the sequence moves forward.",
            'status' => $isDone
                ? ($nextEntry ? 'Next lesson ready' : 'Sequence completed')
                : ($watchProgress > 0 ? "{$watchProgress}% watched" : 'Current lesson still in sequence'),
            'current_lesson' => [
                'id' => $currentEntry['lesson']->id,
                'title' => $currentEntry['lesson']->title,
                'sort_order' => $currentEntry['lesson']->sort_order,
                'url' => route('lessons.show', $currentEntry['lesson']),
                'module_title' => $currentEntry['module']->title,
                'module_url' => route('modules.show', $currentEntry['module']->url_slug),
                'watch_progress' => $watchProgress,
                'is_done' => $isDone,
            ],
            'next_lesson' => $nextEntry ? [
                'id' => $nextEntry['lesson']->id,
                'title' => $nextEntry['lesson']->title,
                'sort_order' => $nextEntry['lesson']->sort_order,
                'url' => route('lessons.show', $nextEntry['lesson']),
                'module_title' => $nextEntry['module']->title,
                'module_url' => route('modules.show', $nextEntry['module']->url_slug),
            ] : null,
            'sequence_rule' => $sequenceRule,
            'supporting_rules' => $supportingRules,
        ];
    }

    protected function buildAssignmentMilestone(Request $request, array $continueLearning): array
    {
        $user = $request->user();
        $tier = $user?->accessTier;
        $learningCta = [
            'label' => (($continueLearning['state'] ?? null) === 'resume')
                ? 'Continue Learning'
                : 'Browse Modules',
            'url' => $continueLearning['cta_url'] ?? route('modules.index'),
        ];

        if (! $user || ! $tier) {
            return [
                'state' => 'empty',
                'eyebrow' => 'Assignment Milestone',
                'title' => 'Assignment milestone will appear here.',
                'description' => 'Home needs an active student access tier before it can explain whether assignment belongs to this YogaFX journey.',
                'status' => 'Awaiting access tier',
                'eligibility_label' => 'Tier eligibility unknown',
                'cta_label' => 'Open Profile',
                'cta_url' => route('profile.edit'),
                'checklist' => [],
                'latest_feedback' => null,
                'latest_submission_at' => null,
                'support_note' => 'Student-side assignment submission is still not active, so this milestone stays informational until the submission flow is introduced.',
            ];
        }

        if ($tier->slug !== AccessTier::SLUG_ONLINE) {
            return [
                'state' => 'not_available',
                'eyebrow' => 'Assignment Milestone',
                'title' => 'Assignment is not included in your current tier.',
                'description' => $tier->slug === AccessTier::SLUG_MASTER_CLASS
                    ? 'Your current YogaFX path emphasizes premium lesson access and certificate journey without the assignment milestone.'
                    : 'Starter Kit keeps the journey lighter, so assignment milestone is intentionally not part of this tier.',
                'status' => 'Not available for your tier',
                'eligibility_label' => 'Unavailable in '.($tier->name ?? 'current tier'),
                'cta_label' => 'Browse Modules',
                'cta_url' => route('modules.index'),
                'checklist' => [],
                'latest_feedback' => null,
                'latest_submission_at' => null,
                'support_note' => 'Home still keeps this milestone visible so students understand what is and is not included in the current membership path.',
            ];
        }

        $submissions = AssignmentSubmission::query()
            ->where('user_id', $user->id)
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->get();

        $submittedEntries = $submissions
            ->filter(fn (AssignmentSubmission $submission) => filled($submission->assignment_video))
            ->values();

        $latestFeedback = $submissions
            ->first(fn (AssignmentSubmission $submission) => filled($submission->assignment_feedback));

        $standingSubmission = $submittedEntries
            ->first(fn (AssignmentSubmission $submission) => str($submission->assignment_type)->lower()->contains('standing'));
        $floorSubmission = $submittedEntries
            ->first(fn (AssignmentSubmission $submission) => str($submission->assignment_type)->lower()->contains('floor'));
        $legacySubmission = $submittedEntries
            ->first(fn (AssignmentSubmission $submission) => str($submission->assignment_type)->lower()->value() === 'graduation_video');

        $hasStanding = (bool) $standingSubmission;
        $hasFloor = (bool) $floorSubmission;
        $hasLegacy = (bool) $legacySubmission;
        $hasCompletePackage = $hasLegacy || ($hasStanding && $hasFloor);

        $relevantSubmissions = $hasLegacy
            ? collect([$legacySubmission])->filter()
            : collect([$standingSubmission, $floorSubmission])->filter();

        $hasRejected = $relevantSubmissions
            ->contains(fn (AssignmentSubmission $submission) => $submission->assignment_status === AssignmentSubmission::STATUS_REJECTED);
        $hasPendingReview = $relevantSubmissions
            ->contains(fn (AssignmentSubmission $submission) => $submission->assignment_status === AssignmentSubmission::STATUS_PENDING_REVIEW);
        $hasApproved = $relevantSubmissions->isNotEmpty()
            && $relevantSubmissions->every(
                fn (AssignmentSubmission $submission) => $submission->assignment_status === AssignmentSubmission::STATUS_APPROVED
            );
        $hasDraftSubmission = $relevantSubmissions
            ->contains(fn (AssignmentSubmission $submission) => blank($submission->submitted_at));
        $latestSubmittedAt = $relevantSubmissions
            ->sortByDesc(fn (AssignmentSubmission $submission) => sprintf(
                '%010d-%010d',
                $submission->submitted_at?->getTimestamp() ?? 0,
                $submission->id,
            ))
            ->first()?->submitted_at;

        if ($submittedEntries->isEmpty()) {
            $state = 'not_started';
        } elseif ($hasRejected) {
            $state = 'rejected';
        } elseif ($hasApproved && $hasCompletePackage) {
            $state = 'approved';
        } elseif ($hasCompletePackage && $hasPendingReview && ! $hasDraftSubmission) {
            $state = 'under_review';
        } else {
            $state = 'submitted';
        }

        $checklist = collect([
            [
                'label' => 'Full Standing Dialog',
                'status' => $hasStanding ? 'Submitted' : 'Pending',
                'detail' => $hasStanding
                    ? 'Standing dialog submission is already recorded for this student.'
                    : 'Standing dialog submission has not been recorded yet.',
            ],
            [
                'label' => 'Full Floor Dialog',
                'status' => $hasFloor ? 'Submitted' : 'Pending',
                'detail' => $hasFloor
                    ? 'Floor dialog submission is already recorded for this student.'
                    : 'Floor dialog submission has not been recorded yet.',
            ],
            [
                'label' => 'Legacy graduation video',
                'status' => $hasLegacy ? 'On record' : 'Not used',
                'detail' => $hasLegacy
                    ? 'A legacy graduation video exists, so Home treats the assignment package as already submitted.'
                    : 'No legacy graduation video is attached to this student record.',
            ],
        ])->values();

        $payload = [
            'state' => $state,
            'eyebrow' => 'Assignment Milestone',
            'title' => 'Assignment milestone is active for your YogaFX path.',
            'description' => 'Home tracks assignment as a major milestone for Online students, while keeping the experience honest about which student-side flows are already live.',
            'status' => 'Assignment tracked',
            'eligibility_label' => 'Included in Online tier',
            'cta_label' => $learningCta['label'],
            'cta_url' => $learningCta['url'],
            'checklist' => $checklist,
            'latest_feedback' => $latestFeedback ? [
                'message' => $latestFeedback->assignment_feedback,
                'status' => str($latestFeedback->assignment_status)->replace('_', ' ')->title()->value(),
            ] : null,
            'latest_submission_at' => optional($latestSubmittedAt)->format('Y-m-d H:i'),
            'support_note' => 'Student-side assignment submission page is still not active, so this milestone currently reflects assignment data that already exists in YogaFX and keeps CTA on safe learning routes.',
        ];

        return match ($state) {
            'not_started' => array_merge($payload, [
                'title' => 'Assignment milestone is waiting for your first submission.',
                'description' => 'You are in the Online tier, so assignment belongs to your journey. Home can already show the milestone, even though the dedicated student submission page is not active yet.',
                'status' => 'Not started',
                'cta_label' => $learningCta['label'],
            ]),
            'submitted' => array_merge($payload, [
                'title' => 'Your assignment journey has started.',
                'description' => $hasCompletePackage
                    ? 'Home found assignment material on record, but the full review state is not final yet.'
                    : 'At least one assignment submission is already on record, but the full package is not complete yet.',
                'status' => $hasCompletePackage ? 'Submitted' : 'Partially submitted',
            ]),
            'under_review' => array_merge($payload, [
                'title' => 'Your assignment is currently under review.',
                'description' => 'Home found a complete assignment package with pending review status, so the best next move is to keep learning while YogaFX reviews it.',
                'status' => 'Under review',
            ]),
            'approved' => array_merge($payload, [
                'title' => 'Your assignment milestone has been approved.',
                'description' => 'The current assignment package has already cleared review, so this milestone is complete in your Home journey.',
                'status' => 'Approved',
            ]),
            'rejected' => array_merge($payload, [
                'title' => 'Your assignment needs another pass.',
                'description' => 'Home found a rejected assignment review, so the milestone stays visible together with the latest feedback that already exists in the system.',
                'status' => 'Rejected',
                'cta_label' => 'Review Modules Again',
                'cta_url' => route('modules.index'),
            ]),
            default => $payload,
        };
    }

    protected function buildCertificateMilestone(Request $request, array $progressSummary, array $continueLearning): array
    {
        $user = $request->user();
        $tier = $user?->accessTier;
        $learningCta = [
            'label' => (($continueLearning['state'] ?? null) === 'resume')
                ? 'Continue Learning'
                : 'Browse Modules',
            'url' => $continueLearning['cta_url'] ?? route('modules.index'),
            'kind' => 'link',
        ];

        if (! $user || ! $tier) {
            return [
                'state' => 'empty',
                'eyebrow' => 'Certificate Milestone',
                'title' => 'Certificate milestone will appear here.',
                'description' => 'Home needs an active student access tier before it can explain whether certificate belongs to this YogaFX journey.',
                'status' => 'Awaiting access tier',
                'eligibility_label' => 'Tier eligibility unknown',
                'cta_label' => 'Open Profile',
                'cta_url' => route('profile.edit'),
                'cta_kind' => 'link',
                'milestones' => [],
                'latest_certificate' => null,
                'support_note' => 'Student certificate page is still not active, so Home surfaces certificate status directly inside the dashboard.',
            ];
        }

        if ($tier->slug === AccessTier::SLUG_STARTER_KIT) {
            return [
                'state' => 'not_available',
                'eyebrow' => 'Certificate Milestone',
                'title' => 'Certificate is not included in your current tier.',
                'description' => 'Starter Kit focuses on a lighter YogaFX path, so certificate milestone is intentionally not part of this membership level.',
                'status' => 'Not available for your tier',
                'eligibility_label' => 'Unavailable in '.($tier->name ?? 'current tier'),
                'cta_label' => 'Browse Modules',
                'cta_url' => route('modules.index'),
                'cta_kind' => 'link',
                'milestones' => [],
                'latest_certificate' => null,
                'support_note' => 'Home still keeps the certificate milestone visible so students understand what belongs to the current tier and what does not.',
            ];
        }

        $certificateRecords = Certificate::query()
            ->where('user_id', $user->id)
            ->latest('generated_at')
            ->latest('id')
            ->get();

        $latestCertificate = $certificateRecords->first();
        $overallProgress = (int) ($progressSummary['overall_progress_percentage'] ?? 0);
        $currentPathCompleted = $overallProgress === 100;
        $hasCertificate = (bool) $latestCertificate;

        $state = $hasCertificate
            ? 'download_available'
            : ($currentPathCompleted ? 'ready' : 'in_progress');

        $milestones = collect([
            [
                'label' => 'Tier entitlement',
                'status' => 'Eligible',
                'detail' => 'This tier is eligible for certificate milestone under the current YogaFX rules.',
            ],
            [
                'label' => 'Learning path progress',
                'status' => $currentPathCompleted ? 'Completed' : "{$overallProgress}% complete",
                'detail' => $currentPathCompleted
                    ? 'Your currently accessible learning path has reached full completion.'
                    : 'Certificate momentum keeps following the completed lessons in your current accessible path.',
            ],
            [
                'label' => 'Certificate record',
                'status' => $hasCertificate ? 'Available' : 'Not generated yet',
                'detail' => $hasCertificate
                    ? 'At least one certificate record already exists for this student.'
                    : 'No generated certificate record exists yet in YogaFX.',
            ],
        ])->values();

        $payload = [
            'state' => $state,
            'eyebrow' => 'Certificate Milestone',
            'title' => 'Certificate milestone is active for your YogaFX path.',
            'description' => 'Home keeps certificate visible as a major milestone, while staying honest about the fact that student-side certificate browsing is not a separate page yet.',
            'status' => 'Certificate tracked',
            'eligibility_label' => 'Included in '.($tier->name ?? 'eligible tier'),
            'cta_label' => $learningCta['label'],
            'cta_url' => $learningCta['url'],
            'cta_kind' => $learningCta['kind'],
            'milestones' => $milestones,
            'latest_certificate' => $latestCertificate ? [
                'type_label' => $latestCertificate->typeLabel(),
                'version' => $latestCertificate->version,
                'generated_at' => optional($latestCertificate->generated_at)->format('Y-m-d H:i'),
                'download_url' => route('student.certificates.download', $latestCertificate),
            ] : null,
            'support_note' => 'Home shows certificate status inside the dashboard first. A dedicated student certificate page is still not part of the active product scope.',
        ];

        return match ($state) {
            'in_progress' => array_merge($payload, [
                'title' => 'Your certificate milestone is still in progress.',
                'description' => 'Certificate belongs to this tier, but the current accessible learning path is not complete yet. Home keeps the milestone visible so the end goal stays clear.',
                'status' => 'In progress',
            ]),
            'ready' => array_merge($payload, [
                'title' => 'Your certificate looks ready from the learning side.',
                'description' => 'Home sees a completed accessible learning path, but no generated certificate record exists yet. This means the milestone is ready from progress perspective and still waiting for certificate generation.',
                'status' => 'Ready for generation',
                'cta_label' => 'Review Modules',
                'cta_url' => route('modules.index'),
            ]),
            'download_available' => array_merge($payload, [
                'title' => 'Your latest certificate is ready to download.',
                'description' => 'YogaFX already has a generated certificate record for this student, so Home can surface it directly as a milestone without sending you into a separate certificate page.',
                'status' => 'Download available',
                'cta_label' => 'Download Latest Certificate',
                'cta_url' => route('student.certificates.download', $latestCertificate),
                'cta_kind' => 'download',
            ]),
            default => $payload,
        };
    }

    protected function buildEbookResourcesSection(Request $request): array
    {
        $user = $request->user();
        $tier = $user?->accessTier;

        if (! $user || ! $user->access_tier_id || ! $tier) {
            return [
                'state' => 'empty',
                'eyebrow' => 'Ebooks & Resources',
                'title' => 'Your supporting resources will appear here.',
                'description' => 'Home needs an active access tier before it can reveal the ebooks and supporting resources that belong to this YogaFX path.',
                'items' => [],
                'summary' => [
                    'total' => 0,
                    'tier_name' => null,
                ],
                'support_note' => 'Ebook preview remains the primary access pattern, with download staying as a separate explicit action.',
            ];
        }

        $ebooks = Ebook::query()
            ->whereHas('accessTiers', fn ($query) => $query->where('access_tiers.id', $user->access_tier_id))
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get();

        if ($ebooks->isEmpty()) {
            return [
                'state' => 'empty',
                'eyebrow' => 'Ebooks & Resources',
                'title' => 'No supporting ebook is available in this tier yet.',
                'description' => 'Home keeps this section light when no ebook is available, so supporting resources never overpower the learning flow.',
                'items' => [],
                'summary' => [
                    'total' => 0,
                    'tier_name' => $tier->name,
                ],
                'support_note' => 'When an ebook is attached to this tier later, Home can surface it here without changing the rest of the student journey.',
            ];
        }

        $items = $ebooks->map(function (Ebook $ebook, int $index) {
            $extension = strtoupper((string) pathinfo((string) $ebook->file, PATHINFO_EXTENSION));
            $formatLabel = $extension !== '' ? $extension : 'FILE';
            $fileName = basename((string) $ebook->file);

            return [
                'id' => $ebook->id,
                'title' => $ebook->title ?: 'Untitled Resource',
                'sort_order' => $ebook->sort_order,
                'file_name' => $fileName !== '' ? $fileName : 'resource-file',
                'format_label' => $formatLabel,
                'preview_url' => route('ebooks.preview', $ebook),
                'download_url' => $this->protectedMediaUrl(
                    'ebook',
                    $ebook->id,
                    'file',
                    $ebook->file,
                    download: true,
                    versionSeed: $ebook->updated_at,
                ),
                'eyebrow' => $index === 0 ? 'Featured Resource' : 'Supporting Resource',
                'description' => $formatLabel === 'PDF'
                    ? 'Preview this ebook in the browser first, then download it explicitly if you want an offline copy.'
                    : 'Open the ebook preview page first. If preview is limited, YogaFX will still keep download available there.',
            ];
        })->values();

        return [
            'state' => 'ready',
            'eyebrow' => 'Ebooks & Resources',
            'title' => 'Supporting resources for your YogaFX practice',
            'description' => 'These ebooks sit behind the main learning flow, giving students extra material without distracting from the next lesson or milestone.',
            'items' => $items,
            'summary' => [
                'total' => $items->count(),
                'tier_name' => $tier->name,
            ],
            'support_note' => 'Preview stays primary, download stays explicit, and the whole section remains tier-aware.',
        ];
    }

    protected function buildHomeExperience(
        Request $request,
        array $continueLearning,
        array $progressSummary,
        array $availableModulesSection,
        array $certificateMilestone,
        array $ebookResourcesSection,
    ): array {
        $user = $request->user();
        $tier = $user?->accessTier;
        $lessonsCompleted = (int) ($progressSummary['lessons_completed'] ?? 0);
        $lessonsTotal = (int) ($progressSummary['lessons_total'] ?? 0);
        $overallProgress = (int) ($progressSummary['overall_progress_percentage'] ?? 0);
        $hasModules = ! empty($availableModulesSection['items']);
        $hasResources = ! empty($ebookResourcesSection['items']);
        $isNewStudent = ($continueLearning['state'] ?? null) === 'start' && $lessonsCompleted === 0;
        $isJourneyComplete = $lessonsTotal > 0 && $overallProgress === 100;

        $defaultState = [
            'state' => 'active',
            'hero_title' => 'Your premium YogaFX learning home is now ready to carry your student identity.',
            'hero_description' => 'Your Home experience is anchored to the current access tier and now has the core student context needed for every learning-focused section below.',
            'primary_cta_label' => 'Continue the Course',
            'primary_cta_url' => route('modules.index'),
            'primary_cta_kind' => 'link',
            'secondary_cta_label' => 'Explore Modules',
            'secondary_cta_url' => route('modules.index'),
            'hero_badges' => [
                $tier?->name ?? 'Tier assignment pending',
                'Learning momentum is active',
            ],
            'highlight_label' => 'Learning home',
            'highlight_value' => $lessonsTotal > 0 ? "{$overallProgress}%" : 'Ready',
            'highlight_caption' => $lessonsTotal > 0
                ? 'Current accessible path'
                : 'Home is waiting for the first lesson signal',
        ];

        if (! $user || ! $tier) {
            return [
                'state' => 'needs_access_tier',
                'hero_title' => 'Complete your YogaFX access setup before the learning journey begins.',
                'hero_description' => 'Home is already safe to open, but it still needs a completed profile and an active access tier before it can guide lessons, milestones, and resources correctly.',
                'primary_cta_label' => 'Open Profile',
                'primary_cta_url' => route('profile.edit'),
                'primary_cta_kind' => 'link',
                'secondary_cta_label' => 'Browse Modules',
                'secondary_cta_url' => route('modules.index'),
                'hero_badges' => [
                    'Profile and tier setup pending',
                    'Home is in safe fallback mode',
                ],
                'highlight_label' => 'Next step',
                'highlight_value' => 'Profile',
                'highlight_caption' => 'Finish setup so learning data can unlock',
            ];
        }

        if ($isNewStudent) {
            return [
                'state' => 'new_student',
                'hero_title' => 'Your first YogaFX lesson is ready whenever you are.',
                'hero_description' => 'This Home is now prepared for a first-time student experience: clear starting point, calm guidance, and enough context to begin without guesswork.',
                'primary_cta_label' => $continueLearning['cta_label'] ?? 'Start First Lesson',
                'primary_cta_url' => $continueLearning['cta_url'] ?? route('modules.index'),
                'primary_cta_kind' => 'link',
                'secondary_cta_label' => 'Explore Modules',
                'secondary_cta_url' => route('modules.index'),
                'hero_badges' => [
                    $tier->name,
                    'First lesson ready to start',
                ],
                'highlight_label' => 'Starting point',
                'highlight_value' => 'Lesson 1',
                'highlight_caption' => 'Home found a safe first lesson for this journey',
            ];
        }

        if (! $hasModules) {
            return [
                'state' => 'catalog_empty',
                'hero_title' => 'Your YogaFX Home is ready, but this tier still has no module to open.',
                'hero_description' => 'The page remains stable and premium even when content is not attached yet. As soon as accessible modules exist, Home will start guiding the learning journey automatically.',
                'primary_cta_label' => 'Browse Modules',
                'primary_cta_url' => route('modules.index'),
                'primary_cta_kind' => 'link',
                'secondary_cta_label' => 'Open Profile',
                'secondary_cta_url' => route('profile.edit'),
                'hero_badges' => [
                    $tier->name,
                    'Catalog not available yet',
                ],
                'highlight_label' => 'Catalog state',
                'highlight_value' => 'Waiting',
                'highlight_caption' => 'No accessible modules found for this tier yet',
            ];
        }

        if ($isJourneyComplete) {
            $primaryCtaLabel = $certificateMilestone['cta_label'] ?? 'Review Modules';
            $primaryCtaUrl = $certificateMilestone['cta_url'] ?? route('modules.index');
            $primaryCtaKind = $certificateMilestone['cta_kind'] ?? 'link';
            $secondaryCtaLabel = $hasResources ? 'Open Ebooks' : 'Review Modules';
            $secondaryCtaUrl = $hasResources ? route('ebooks.index') : route('modules.index');

            return [
                'state' => 'journey_complete',
                'hero_title' => 'Your current YogaFX learning path is complete.',
                'hero_description' => 'Home shifts from momentum into celebration and next-possibility mode: certificate if available, supporting resources if present, and room to revisit the path without confusion.',
                'primary_cta_label' => $primaryCtaLabel,
                'primary_cta_url' => $primaryCtaUrl,
                'primary_cta_kind' => $primaryCtaKind,
                'secondary_cta_label' => $secondaryCtaLabel,
                'secondary_cta_url' => $secondaryCtaUrl,
                'hero_badges' => [
                    $tier->name,
                    'Current path completed',
                ],
                'highlight_label' => 'Completion',
                'highlight_value' => '100%',
                'highlight_caption' => 'The accessible lesson journey is complete',
            ];
        }

        return $defaultState;
    }

    protected function lessonProgressMap(?int $userId, iterable $lessonIds): Collection
    {
        $lessonIds = collect($lessonIds)->filter()->values();

        if (! $userId || $lessonIds->isEmpty()) {
            return collect();
        }

        return LessonProgress::query()
            ->where('user_id', $userId)
            ->whereIn('lesson_id', $lessonIds)
            ->get()
            ->keyBy('lesson_id');
    }

    protected function latestProgressLessonId(?int $userId, Collection $lessonProgressMap): ?int
    {
        if (! $userId || $lessonProgressMap->isEmpty()) {
            return null;
        }

        return $lessonProgressMap
            ->sortByDesc(fn (LessonProgress $progress) => sprintf(
                '%010d-%010d',
                $progress->updated_at?->getTimestamp() ?? 0,
                $progress->id,
            ))
            ->keys()
            ->first();
    }
}
