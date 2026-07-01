<?php

namespace App\Services\Mobile\V1;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\AssessmentAttempt;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Ebook;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Module;
use App\Models\StudentModuleVisit;
use App\Models\User;
use App\Services\Mobile\V1\Concerns\BuildsMobileSignedContentImageUrls;
use App\Services\BunnyStreamService;
use App\Services\Certificates\CertificateEligibilityService;
use App\Services\StudentLearningPathService;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class StudentModuleApiService
{
    use BuildsMobileSignedContentImageUrls;

    private const CERTIFICATE_DOWNLOAD_SLUG = 'certificate-download';

    public function __construct(
        private readonly BunnyStreamService $bunnyStreamService,
        private readonly CertificateEligibilityService $certificateEligibilityService,
        private readonly StudentLearningPathService $studentLearningPathService,
    ) {}

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function moduleItemsForUser(User $user): Collection
    {
        $accessTierId = $user->access_tier_id;

        if (! $accessTierId) {
            return collect();
        }

        $modules = $this->accessibleModulesWithLessons($user);
        $resourceModuleVisitMap = $this->resourceModuleVisitMap($user->id, $modules->pluck('id'));
        $lessonProgressMap = $this->lessonProgressMap(
            $user->id,
            $modules->flatMap(fn (Module $module) => $module->lessons->pluck('id')),
        );
        $completedAssessmentIds = $this->completedAssessmentIds(
            $user->id,
            $modules->flatMap(fn (Module $module) => $module->lessons->pluck('assessment_id'))->filter(),
        );
        $assignmentSubmissionMap = $this->assignmentSubmissionMap(
            $user->id,
            $modules->flatMap(fn (Module $module) => $module->assignments->pluck('id')),
        );
        $moduleAccessMap = $this->moduleAccessMap(
            $user,
            $modules,
            $lessonProgressMap,
            $completedAssessmentIds,
            $assignmentSubmissionMap,
            $resourceModuleVisitMap,
        );
        $activeLessonId = $this->latestProgressLessonId($lessonProgressMap);

        return $modules->map(function (Module $module) use ($user, $moduleAccessMap, $lessonProgressMap, $completedAssessmentIds, $activeLessonId) {
            $moduleAccess = $moduleAccessMap->get($module->id, [
                'is_visible' => false,
                'status' => 'locked',
                'is_complete' => false,
            ]);
            $totalLessons = $module->lessons->count();
            $totalAssignments = $module->assignments->count();
            $completedLessons = $module->lessons->filter(
                fn (Lesson $lesson) => $this->isLessonFullyComplete(
                    $lesson,
                    $lessonProgressMap->get($lesson->id),
                    $completedAssessmentIds,
                )
            )->count();
            $isActive = $module->lessons->contains(fn (Lesson $lesson) => $lesson->id === $activeLessonId);
            $status = $moduleAccess['status'] === 'available' && $isActive
                ? 'active'
                : $moduleAccess['status'];

            $continueLessonId = $activeLessonId ?? $module->lessons->first()?->id;
            $continueLessonUrl = $continueLessonId ? (string) $continueLessonId : null;

            // --- SOURCE OF TRUTH UNTUK MOBILE LIST ---
            $viewTypes = [];
            if ($totalLessons > 0) $viewTypes[] = 'lesson';
            if ($module->ebook_enabled) $viewTypes[] = 'ebook';
            if ($module->video_lecturer_enabled) $viewTypes[] = 'video_lecturer';
            if ($module->certificate_enabled) $viewTypes[] = 'certificate';
            if ($totalAssignments > 0) $viewTypes[] = 'assignment';

            $primaryCtaLabel = 'Open Module';
            $primaryCtaKind = 'play';
            $primaryCtaUrl = $continueLessonUrl ?? null;

            if ($module->certificate_enabled) {
                $primaryCtaLabel = 'View Certificate';
                $primaryCtaKind = 'download';
                $primaryCtaUrl = null;
            } elseif ($module->ebook_enabled && ! $module->video_lecturer_enabled && $totalLessons === 0) {
                $primaryCtaLabel = 'Read Ebook';
                $primaryCtaKind = 'document';
                $primaryCtaUrl = null;
            } elseif ($totalLessons > 0) {
                $primaryCtaLabel = 'Continue Last Lesson';
                $primaryCtaKind = 'play';
                $primaryCtaUrl = $continueLessonUrl;
            } elseif ($module->video_lecturer_enabled) {
                $primaryCtaLabel = 'Watch Videos';
                $primaryCtaKind = 'play';
                $primaryCtaUrl = null;
            }
            // --- END SOURCE OF TRUTH ---

            return [
                'id' => $module->id,
                'title' => $module->title,
                'slug' => $module->url_slug,
                'description' => $moduleAccess['description'] ?? $module->description,
                'sort_order' => $module->sort_order,
                'lesson_count' => $totalLessons,
                'assignments_count' => $totalAssignments,
                'completed_lessons' => $completedLessons,
                'progress_percentage' => $totalLessons > 0
                    ? (int) round(($completedLessons / $totalLessons) * 100)
                    : (($moduleAccess['is_complete'] ?? false) ? 100 : 0),
                'show_progress' => $totalLessons > 0,
                'status' => $status,
                'is_visible' => (bool) ($moduleAccess['is_visible'] ?? false),
                'is_complete' => (bool) ($moduleAccess['is_complete'] ?? false),
                
                // Fields Source of Truth
                'view_types' => $viewTypes,
                'primary_cta_label' => $primaryCtaLabel,
                'primary_cta_url' => $primaryCtaUrl,
                'primary_cta_kind' => $primaryCtaKind,
                
                'certificate_enabled' => (bool) $module->certificate_enabled,
                'ebook_enabled' => (bool) $module->ebook_enabled,
                'video_lecturer_enabled' => (bool) $module->video_lecturer_enabled,
                'thumbnail_url' => $this->mobileSignedContentImageUrl($user, 'module', $module->id, 'thumbnail', $module->thumbnail, $module->updated_at),
            ];
        })->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $moduleItems
     * @return array<string, mixed>
     */
    public function progressSummaryForModuleItems(Collection $moduleItems): array
    {
        $visibleModules = $moduleItems->where('is_visible', true)->values();
        $lessonBearingModules = $visibleModules->where('lesson_count', '>', 0)->values();
        $modulesTotal = $lessonBearingModules->count();
        $modulesCompleted = $lessonBearingModules->where('status', 'completed')->count();
        $lessonsTotal = (int) $lessonBearingModules->sum('lesson_count');
        $lessonsCompleted = (int) $lessonBearingModules->sum('completed_lessons');

        return [
            'modules_total' => $modulesTotal,
            'modules_completed' => $modulesCompleted,
            'lessons_total' => $lessonsTotal,
            'lessons_completed' => $lessonsCompleted,
            'overall_progress_percentage' => $lessonsTotal > 0
                ? (int) round(($lessonsCompleted / $lessonsTotal) * 100)
                : 0,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function continueLearningForUser(User $user): ?array
    {
        $accessTierId = $user->access_tier_id;

        if (! $accessTierId) {
            return null;
        }

        $modules = $this->accessibleModulesWithLessons($user);
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

            return [
                'state' => $latestProgress->is_done ? 'resume_completed' : 'resume',
                'progress_percentage' => $latestProgress->is_done ? 100 : max(0, min(100, $watchProgress)),
                'lesson' => [
                    'id' => $lesson->id,
                    'title' => $lesson->title,
                    'sort_order' => $lesson->sort_order,
                    'thumbnail_url' => $this->lessonThumbnailUrl($user, $lesson, $module),
                ],
                'module' => [
                    'id' => $module->id,
                    'title' => $module->title,
                    'slug' => $module->url_slug,
                ],
            ];
        }

        $startingModule = $modules->first(fn (Module $module) => $module->lessons->isNotEmpty());
        $startingLesson = $startingModule?->lessons->first();

        if ($startingModule && $startingLesson) {
            return [
                'state' => 'start',
                'progress_percentage' => 0,
                'lesson' => [
                    'id' => $startingLesson->id,
                    'title' => $startingLesson->title,
                    'sort_order' => $startingLesson->sort_order,
                    'thumbnail_url' => $this->lessonThumbnailUrl($user, $startingLesson, $startingModule),
                ],
                'module' => [
                    'id' => $startingModule->id,
                    'title' => $startingModule->title,
                    'slug' => $startingModule->url_slug,
                ],
            ];
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function moduleDetailForUser(User $user, int $moduleId): ?array
    {
        $accessTierId = $user->access_tier_id;

        if (! $accessTierId) {
            return null;
        }

        $modules = $this->accessibleModulesWithLessons($user);
        /** @var Module|null $currentModule */
        $currentModule = $modules->firstWhere('id', $moduleId);

        if (! $currentModule) {
            return null;
        }

        $resourceModuleVisitMap = $this->resourceModuleVisitMap($user->id, $modules->pluck('id'));
        $lessonProgressMap = $this->lessonProgressMap(
            $user->id,
            $modules->flatMap(fn (Module $module) => $module->lessons->pluck('id')),
        );
        $completedAssessmentIds = $this->completedAssessmentIds(
            $user->id,
            $modules->flatMap(fn (Module $module) => $module->lessons->pluck('assessment_id'))->filter(),
        );
        $assignmentSubmissionMap = $this->assignmentSubmissionMap(
            $user->id,
            $modules->flatMap(fn (Module $module) => $module->assignments->pluck('id')),
        );
        $moduleAccessMap = $this->moduleAccessMap(
            $user,
            $modules,
            $lessonProgressMap,
            $completedAssessmentIds,
            $assignmentSubmissionMap,
            $resourceModuleVisitMap,
        );
        $currentModuleAccess = $moduleAccessMap->get($currentModule->id);

        if (! ($currentModuleAccess['is_visible'] ?? false)) {
            return [
                'id' => $currentModule->id,
                'title' => $currentModule->title,
                'slug' => $currentModule->url_slug,
                'status' => 'locked',
                'is_visible' => false,
                'lock_reason' => 'Complete the previous module requirements before opening this module.',
                'view_types' => [],
            ];
        }

        if ($this->isCertificateDownloadModule($currentModule)) {
            return [
                'id' => $currentModule->id,
                'title' => $currentModule->title,
                'slug' => $currentModule->url_slug,
                'description' => $currentModule->description,
                'sort_order' => $currentModule->sort_order,
                'view_type' => 'certificate_download',
                'view_types' => ['certificate'],
                'primary_cta_label' => 'View Certificate',
                'primary_cta_kind' => 'download',
                'primary_cta_url' => null,
                'status' => $currentModuleAccess['status'] ?? 'available',
                'is_visible' => true,
                'is_complete' => (bool) ($currentModuleAccess['is_complete'] ?? false),
                'thumbnail_url' => $this->mobileSignedContentImageUrl($user, 'module', $currentModule->id, 'thumbnail', $currentModule->thumbnail, $currentModule->updated_at),
                'certificate' => $currentModuleAccess['certificate_state'] ?? [],
            ];
        }

        $lessonUnlockMap = $this->lessonUnlockMap($modules, $lessonProgressMap, $completedAssessmentIds);
        $activeLessonId = $this->latestProgressLessonId($lessonProgressMap);
        $lessons = $currentModule->lessons;
        $completedLessons = $lessons->filter(
            fn (Lesson $lesson) => $this->isLessonFullyComplete(
                $lesson,
                $lessonProgressMap->get($lesson->id),
                $completedAssessmentIds,
            )
        )->count();

        $continueLessonId = $activeLessonId ?? $lessons->first()?->id;
        $continueLessonUrl = $continueLessonId ? (string) $continueLessonId : null;

        // --- SOURCE OF TRUTH UNTUK MOBILE DETAIL ---
        $viewTypes = [];
        if ($lessons->count() > 0) $viewTypes[] = 'lesson';
        if ($currentModule->ebook_enabled) $viewTypes[] = 'ebook';
        if ($currentModule->video_lecturer_enabled) $viewTypes[] = 'video_lecturer';
        if ($currentModule->certificate_enabled) $viewTypes[] = 'certificate';
        if ($currentModule->assignments->count() > 0) $viewTypes[] = 'assignment';

        $primaryCtaLabel = 'Open Module';
        $primaryCtaKind = 'play';
        $primaryCtaUrl = $continueLessonUrl ?? null;

        if ($currentModule->certificate_enabled) {
            $primaryCtaLabel = 'View Certificates';
            $primaryCtaKind = 'download';
            $primaryCtaUrl = null;
        } elseif ($currentModule->ebook_enabled && ! $currentModule->video_lecturer_enabled && $lessons->count() === 0) {
            $primaryCtaLabel = 'Browse Ebooks';
            $primaryCtaKind = 'document';
            $primaryCtaUrl = null;
        } elseif ($lessons->count() > 0) {
            $primaryCtaLabel = 'Continue Last Lesson';
            $primaryCtaKind = 'play';
            $primaryCtaUrl = $continueLessonUrl;
        } elseif ($currentModule->video_lecturer_enabled) {
            $primaryCtaLabel = 'Watch Videos';
            $primaryCtaKind = 'play';
            $primaryCtaUrl = null; // Backend menentukan URL navigasi ke list video di rute lain
        }
        // --- END SOURCE OF TRUTH ---

        return [
            'id' => $currentModule->id,
            'title' => $currentModule->title,
            'slug' => $currentModule->url_slug,
            'description' => $currentModule->description,
            'sort_order' => $currentModule->sort_order,
            'lesson_count' => $lessons->count(),
            'assignments_count' => $currentModule->assignments->count(),
            'completed_lessons' => $completedLessons,
            'progress_percentage' => $lessons->count() > 0
                ? (int) round(($completedLessons / $lessons->count()) * 100)
                : (($currentModuleAccess['is_complete'] ?? false) ? 100 : 0),
            'show_progress' => $lessons->count() > 0,
            'status' => $currentModuleAccess['status'] ?? 'available',
            'is_visible' => true,
            'is_complete' => (bool) ($currentModuleAccess['is_complete'] ?? false),
            'view_type' => 'learning',

            // Fields Source of Truth (Baru ditambahkan)
            'view_types' => $viewTypes,
            'primary_cta_label' => $primaryCtaLabel,
            'primary_cta_url' => $primaryCtaUrl,
            'primary_cta_kind' => $primaryCtaKind,

            'certificate_enabled' => (bool) $currentModule->certificate_enabled,
            'ebook_enabled' => (bool) $currentModule->ebook_enabled,
            'video_lecturer_enabled' => (bool) $currentModule->video_lecturer_enabled,
            'thumbnail_url' => $this->mobileSignedContentImageUrl($user, 'module', $currentModule->id, 'thumbnail', $currentModule->thumbnail, $currentModule->updated_at),
            'lessons' => $lessons->map(fn (Lesson $lesson) => [
                'id' => $lesson->id,
                'title' => $lesson->title,
                'sort_order' => $lesson->sort_order,
                'has_workbook' => $lesson->workbook !== null,
                'has_video' => $lesson->lesson_video_id !== null,
                'has_audio' => $lesson->audio_url !== null,
                'has_content' => $lesson->content !== null,
                'is_locked' => ! ($lessonUnlockMap->get($lesson->id)['is_unlocked'] ?? false),
                'lock_reason' => $lessonUnlockMap->get($lesson->id)['reason'] ?? null,
                'status' => $this->isLessonFullyComplete(
                    $lesson,
                    $lessonProgressMap->get($lesson->id),
                    $completedAssessmentIds,
                )
                    ? 'completed'
                    : (! ($lessonUnlockMap->get($lesson->id)['is_unlocked'] ?? false)
                        ? 'locked'
                        : ($lesson->id === $activeLessonId ? 'active' : 'available')),
                'progress_percentage' => (int) round((float) (optional($lessonProgressMap->get($lesson->id))->watch_progress ?? 0)),
                'thumbnail_url' => $this->lessonThumbnailUrl($user, $lesson, $currentModule),
            ])->values()->all(),
            'assignments' => $currentModule->assignments
                ->map(function (Assignment $assignment) use ($assignmentSubmissionMap) {
                    $submission = $assignmentSubmissionMap->get($assignment->id);

                    return [
                        'id' => $assignment->id,
                        'title' => $assignment->title,
                        'description' => $assignment->description,
                        'sort_order' => $assignment->sort_order,
                        'status' => $assignment->status,
                        'submission_status' => $submission?->assignment_status,
                        'submission_feedback' => $submission?->assignment_feedback,
                        'submitted_at' => $submission?->submitted_at?->toDateTimeString(),
                    ];
                })
                ->values()
                ->all(),
            'ebooks' => $currentModule->ebook_enabled
                ? $this->ebooksForStudent($user->access_tier_id, $user)
                : [],
            'video_lecturers' => $currentModule->video_lecturer_enabled
                ? $this->videoLecturersForStudent($user)
                : [],
            'certificates' => $currentModule->certificate_enabled
                ? $this->generatedCertificatesForUser($user)
                : [],
            'certificate_summary' => $currentModule->certificate_enabled
                ? [
                    'generated_items' => collect($this->generatedCertificatesForUser($user))
                        ->map(fn (array $certificate) => [
                            'id' => $certificate['id'],
                            'type_label' => $certificate['type_label'],
                            'generated_at' => $certificate['generated_at'],
                        ])
                        ->values()
                        ->all(),
                ]
                : null,
        ];
    }

    private function accessibleModulesWithLessons(User $user): Collection
    {
        return $this->studentLearningPathService->accessibleModulesForStudent($user, withAssessments: true);
    }

    private function moduleAccessMap(
        User $user,
        Collection $modules,
        Collection $lessonProgressMap,
        Collection $completedAssessmentIds,
        Collection $assignmentSubmissionMap,
        Collection $resourceModuleVisitMap,
    ): Collection {
        $accessMap = collect();
        $allPreviousModulesComplete = true;

        foreach ($modules as $module) {
            if ($this->isCertificateDownloadModule($module)) {
                $certificateState = $this->certificateAccessState($user);

                $accessMap->put($module->id, [
                    'is_visible' => (bool) ($certificateState['is_visible'] ?? false),
                    'status' => $certificateState['module_status'] ?? 'locked',
                    'description' => $certificateState['module_description'] ?? $module->description,
                    'is_complete' => (bool) ($certificateState['is_complete'] ?? false),
                    'certificate_state' => $certificateState,
                ]);

                $allPreviousModulesComplete = $allPreviousModulesComplete
                    && (bool) ($certificateState['is_complete'] ?? false);

                continue;
            }

            $isComplete = $this->isModuleFullyComplete(
                $module,
                $lessonProgressMap,
                $completedAssessmentIds,
                $assignmentSubmissionMap,
                $resourceModuleVisitMap,
            );

            $accessMap->put($module->id, [
                'is_visible' => $allPreviousModulesComplete,
                'status' => $isComplete
                    ? 'completed'
                    : ($allPreviousModulesComplete ? 'available' : 'locked'),
                'description' => $module->description,
                'is_complete' => $isComplete,
            ]);

            $allPreviousModulesComplete = $allPreviousModulesComplete && $isComplete;
        }

        return $accessMap;
    }

    private function isModuleFullyComplete(
        Module $module,
        Collection $lessonProgressMap,
        Collection $completedAssessmentIds,
        Collection $assignmentSubmissionMap,
        Collection $resourceModuleVisitMap,
    ): bool {
        $liveAssignments = $module->assignments->where('status', Assignment::STATUS_LIVE);

        if ($module->lessons->isNotEmpty()) {
            return $module->lessons->every(
                fn (Lesson $lesson) => $this->isLessonFullyComplete(
                    $lesson,
                    $lessonProgressMap->get($lesson->id),
                    $completedAssessmentIds,
                ),
            );
        }

        if ($liveAssignments->isNotEmpty()) {
            return $liveAssignments
                ->every(fn (Assignment $assignment) => $this->isAssignmentComplete(
                    $assignmentSubmissionMap->get($assignment->id),
                ));
        }

        if ($this->isCertificateDownloadModule($module)) {
            return false;
        }

        if ($this->isOpenOnceResourceModule($module)) {
            return $resourceModuleVisitMap->has($module->id);
        }

        return false;
    }

    private function isLessonFullyComplete(
        Lesson $lesson,
        ?LessonProgress $lessonProgress,
        Collection $completedAssessmentIds,
    ): bool {
        if ($lesson->lesson_video_id !== null && (float) ($lessonProgress?->watch_progress ?? 0) < 95) {
            return false;
        }

        if (
            $lesson->assessment_id !== null
            && $lesson->assessment?->status === 'live'
            && $lesson->assessment?->is_active
        ) {
            return $completedAssessmentIds->contains((int) $lesson->assessment_id);
        }

        return true;
    }

    private function isAssignmentComplete(?AssignmentSubmission $submission): bool
    {
        return $submission?->assignment_status === AssignmentSubmission::STATUS_APPROVED;
    }

    private function assignmentSubmissionMap(?int $userId, iterable $assignmentIds): Collection
    {
        $assignmentIds = collect($assignmentIds)->filter()->values();

        if (! $userId || $assignmentIds->isEmpty()) {
            return collect();
        }

        return AssignmentSubmission::query()
            ->where('user_id', $userId)
            ->whereIn('assignment_id', $assignmentIds)
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->get()
            ->unique('assignment_id')
            ->keyBy('assignment_id');
    }

    private function lessonProgressMap(?int $userId, iterable $lessonIds): Collection
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

    private function completedAssessmentIds(?int $userId, Collection $assessmentIds): Collection
    {
        if (! $userId || $assessmentIds->isEmpty()) {
            return collect();
        }

        return AssessmentAttempt::query()
            ->where('user_id', $userId)
            ->whereIn('assessment_id', $assessmentIds)
            ->where('status', AssessmentAttempt::STATUS_COMPLETED)
            ->pluck('assessment_id')
            ->map(fn ($value) => (int) $value)
            ->unique()
            ->values();
    }

    private function resourceModuleVisitMap(?int $userId, iterable $moduleIds): Collection
    {
        $moduleIds = collect($moduleIds)->filter()->values();

        if (! $userId || $moduleIds->isEmpty()) {
            return collect();
        }

        return StudentModuleVisit::query()
            ->where('user_id', $userId)
            ->whereIn('module_id', $moduleIds)
            ->pluck('module_id')
            ->map(fn ($moduleId) => (int) $moduleId)
            ->flip();
    }

    private function isCertificateDownloadModule(Module $module): bool
    {
        // Check if it matches the designated slug
        $isSlugMatch = $module->url_slug === self::CERTIFICATE_DOWNLOAD_SLUG;

        // Check if it's implicitly a certificate module (no lessons/assignments, but certificate enabled)
        $isImplicitMatch = $module->lessons->isEmpty()
            && $module->assignments->where('status', Assignment::STATUS_LIVE)->isEmpty()
            && (bool) $module->certificate_enabled;

        return $isSlugMatch || $isImplicitMatch;
    }

    private function isOpenOnceResourceModule(Module $module): bool
    {
        return $module->lessons->isEmpty()
            && $module->assignments->where('status', Assignment::STATUS_LIVE)->isEmpty()
            && ! $this->isCertificateDownloadModule($module)
            && ((bool) $module->ebook_enabled || (bool) $module->video_lecturer_enabled);
    }

    private function latestProgressLessonId(Collection $lessonProgressMap): ?int
    {
        if ($lessonProgressMap->isEmpty()) {
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

    private function lessonUnlockMap(Collection $modules, Collection $lessonProgressMap, Collection $completedAssessmentIds): Collection
    {
        $orderedLessons = $modules->flatMap(fn (Module $module) => $module->lessons)->values();
        $unlockMap = collect();

        foreach ($orderedLessons as $index => $lesson) {
            if ($index === 0) {
                $unlockMap->put($lesson->id, [
                    'is_unlocked' => true,
                    'reason' => null,
                ]);

                continue;
            }

            $previousLesson = $orderedLessons[$index - 1];
            $previousProgress = $lessonProgressMap->get($previousLesson->id);
            $gate = $this->lessonAdvanceGate($previousLesson, $previousProgress, $completedAssessmentIds);

            $unlockMap->put($lesson->id, $gate);
        }

        return $unlockMap;
    }

    private function lessonAdvanceGate(
        Lesson $lesson,
        ?LessonProgress $lessonProgress,
        Collection $completedAssessmentIds,
    ): array {
        $watchProgress = (float) ($lessonProgress?->watch_progress ?? 0);

        if ($lesson->lesson_video_id !== null && $watchProgress < 95) {
            return [
                'is_unlocked' => false,
                'reason' => 'Complete the lesson video to at least 95% before continuing.',
            ];
        }

        if (
            $lesson->assessment_id !== null
            && $lesson->assessment?->status === 'live'
            && $lesson->assessment?->is_active
            && ! $completedAssessmentIds->contains((int) $lesson->assessment_id)
        ) {
            return [
                'is_unlocked' => false,
                'reason' => 'Complete the lesson assessment before continuing.',
            ];
        }

        return [
            'is_unlocked' => true,
            'reason' => null,
        ];
    }

    private function lessonThumbnailUrl(User $user, Lesson $lesson, Module $module): ?string
    {
        return $this->mobileSignedContentImageUrl($user, 'lesson', $lesson->id, 'thumbnail', $lesson->thumbnail, $lesson->updated_at)
            ?: $this->bunnyStreamService->thumbnailUrl($lesson->lesson_video_id)
            ?: $this->mobileSignedContentImageUrl($user, 'module', $module->id, 'thumbnail', $module->thumbnail, $module->updated_at);
    }

    private function ebooksForStudent(?int $accessTierId, User $user): array
    {
        return Ebook::query()
            ->whereHas('accessTiers', fn ($query) => $query->where('access_tiers.id', $accessTierId))
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get()
            ->map(function (Ebook $ebook) use ($user) {
                $downloadUrl = $this->protectedMediaUrl(
                    'ebook',
                    $ebook->id,
                    'file',
                    $ebook->file,
                    download: true,
                    versionSeed: $ebook->updated_at,
                );
                $previewUrl = route('mobile.api.v1.ebooks.show', $ebook);

                return [
                    'id' => $ebook->id,
                    'title' => $ebook->title,
                    'sort_order' => $ebook->sort_order,
                    'file_name' => basename((string) $ebook->file),
                    'preview_url' => $previewUrl,
                    'download_url' => $downloadUrl,
                    'open_url' => $downloadUrl,
                ];
            })
            ->values()
            ->all();
    }

    private function videoLecturersForStudent(User $user): array
    {
        $accessTierId = $user->access_tier_id;

        return Course::query()
            ->whereHas('accessTiers', fn ($query) => $query->where('access_tiers.id', $accessTierId))
            ->orderBy('title')
            ->get()
            ->values()
            ->map(function (Course $course, int $index) use ($user) {
                $videoState = $this->videoStateForCourse($course);

                return [
                    'id' => $course->id,
                    'title' => $course->title,
                    'url_slug' => $course->url_slug,
                    'description' => $course->description,
                    'index' => $index + 1,
                    'status' => $videoState['is_ready'] ? 'ready' : 'unavailable',
                    'thumbnail_url' => $this->mobileSignedContentImageUrl($user, 'course', $course->id, 'thumbnail', $course->thumbnail, $course->updated_at)
                        ?: $this->bunnyStreamService->thumbnailUrl($course->video),
                    'video' => $videoState,
                ];
            })
            ->all();
    }

    private function generatedCertificatesForUser(User $user): array
    {
        return $this->certificateEligibilityService
            ->latestCertificatesByType($user, $this->certificateEligibilityService->summaryForStudent($user)['available_types'])
            ->sortByDesc(fn (Certificate $certificate) => sprintf(
                '%010d-%010d',
                $certificate->generated_at?->getTimestamp() ?? 0,
                $certificate->id,
            ))
            ->values()
            ->map(fn (Certificate $certificate) => [
                'id' => $certificate->id,
                'type_label' => $certificate->typeLabel(),
                'generated_at' => optional($certificate->generated_at)->toDateTimeString(),
                'download_url' => route('mobile.api.v1.certificates.download', $certificate),
                'open_url' => route('mobile.api.v1.certificates.show', $certificate),
            ])
            ->all();
    }

    private function certificateAccessState(User $user): array
    {
        $summary = $this->certificateEligibilityService->summaryForStudent($user);
        $availableTypes = $summary['available_types'] ?? [];
        $generatedCertificates = $this->certificateEligibilityService
            ->latestCertificatesByType($user, $availableTypes)
            ->sortByDesc(fn (Certificate $certificate) => sprintf(
                '%010d-%010d',
                $certificate->generated_at?->getTimestamp() ?? 0,
                $certificate->id,
            ))
            ->values();
        $latestCertificate = $generatedCertificates->first();
        $eligibleTier = $user->access_tier_id !== null && collect($availableTypes)->isNotEmpty();
        $learningEligible = (bool) ($summary['learning_eligible'] ?? false);
        $hasCertificate = $generatedCertificates->isNotEmpty();
        $isVisible = $eligibleTier && ($learningEligible || $hasCertificate);
        $state = ! $eligibleTier
            ? 'not_available'
            : ($hasCertificate ? 'generated' : ($learningEligible ? 'ready' : 'locked'));

        return [
            'state' => $state,
            'is_visible' => $isVisible,
            'is_complete' => $hasCertificate,
            'module_status' => $hasCertificate ? 'completed' : ($learningEligible ? 'available' : 'locked'),
            'module_description' => $hasCertificate
                ? 'Your certificate has been generated. This module is now complete and every module after it is unlocked.'
                : ($learningEligible
                    ? 'All required submitted assignment videos have been approved. This certificate module is now ready for admin generation.'
                    : 'Certificate access unlocks after all required submitted assignment videos in this certificate path are approved by admin.'),
            'title' => $hasCertificate
                ? 'Your latest certificate is ready to download.'
                : ($learningEligible
                    ? 'Your certificate milestone is ready from approved assignment videos.'
                    : 'Certificate access is not unlocked yet.'),
            'description' => $hasCertificate
                ? 'This module now acts as your student certificate area. Download the latest certificate record generated for your account.'
                : ($learningEligible
                    ? 'All required submitted assignment videos have been approved. If the certificate file has not been generated yet, please wait for the YogaFX team to finalize it.'
                    : 'Certificate access opens after all required submitted assignment videos for your current certificate path have been approved by admin.'),
            'eligibility_label' => $eligibleTier
                ? 'Certificate included in '.($summary['tier']['name'] ?? 'your current tier')
                : 'Certificate not available in this tier',
            'support_note' => $hasCertificate
                ? 'The latest available certificate record is surfaced here and later modules stay unlocked after generation.'
                : 'This page opens as soon as the required submitted videos are approved, even if the final file is still waiting to be generated.',
            'requirements' => $summary['requirements'] ?? [],
            'learning_eligible' => $learningEligible,
            'has_required_name' => (bool) ($summary['has_required_name'] ?? false),
            'eligibility_message' => $summary['message'] ?? null,
            'latest_certificate' => $latestCertificate ? [
                'id' => $latestCertificate->id,
                'type_label' => $latestCertificate->typeLabel(),
                'version' => $latestCertificate->version,
                'generated_at' => optional($latestCertificate->generated_at)->toDateTimeString(),
                'download_url' => route('mobile.api.v1.certificates.download', $latestCertificate),
                'open_url' => route('mobile.api.v1.certificates.show', $latestCertificate),
            ] : null,
            'cta_label' => $hasCertificate ? 'Download Latest Certificate' : 'Browse Modules',
            'cta_url' => $hasCertificate
                ? route('mobile.api.v1.certificates.download', $latestCertificate)
                : route('mobile.api.v1.modules.index'),
            'cta_kind' => $hasCertificate ? 'download' : 'link',
        ];
    }

    /**
     * @return array{
     * video_id: string|null,
     * hls_url: string|null,
     * is_ready: bool,
     * is_configured: bool,
     * is_valid_id: bool,
     * is_found_in_library: bool|null,
     * warning_message: string|null
     * }
     */
    private function videoStateForCourse(Course $course): array
    {
        $videoId = is_string($course->video)
            ? trim($course->video)
            : null;

        if (! filled($videoId)) {
            return [
                'video_id' => null,
                'hls_url' => null,
                'is_ready' => false,
                'is_configured' => $this->bunnyStreamService->hasPlaybackConfig(),
                'is_valid_id' => false,
                'is_found_in_library' => null,
                'warning_message' => null,
            ];
        }

        if (! Str::isUuid($videoId)) {
            return [
                'video_id' => $videoId,
                'hls_url' => null,
                'is_ready' => false,
                'is_configured' => $this->bunnyStreamService->hasPlaybackConfig(),
                'is_valid_id' => false,
                'is_found_in_library' => null,
                'warning_message' => 'This lecturer video is not using a valid Bunny Stream video ID yet.',
            ];
        }

        if (! $this->bunnyStreamService->hasPlaybackConfig()) {
            return [
                'video_id' => $videoId,
                'hls_url' => null,
                'is_ready' => false,
                'is_configured' => false,
                'is_valid_id' => true,
                'is_found_in_library' => null,
                'warning_message' => 'Bunny Stream CDN is not configured yet in the current environment.',
            ];
        }

        $videoInspection = $this->bunnyStreamService->inspectVideoId($videoId);

        if ($videoInspection['is_verified'] && ! $videoInspection['is_found']) {
            return [
                'video_id' => $videoId,
                'hls_url' => null,
                'is_ready' => false,
                'is_configured' => true,
                'is_valid_id' => true,
                'is_found_in_library' => false,
                'warning_message' => 'This lecturer video ID was not found in the configured Bunny Stream library.',
            ];
        }

        return [
            'video_id' => $videoId,
            'hls_url' => $this->bunnyStreamService->hlsUrl($videoId),
            'is_ready' => true,
            'is_configured' => true,
            'is_valid_id' => true,
            'is_found_in_library' => $videoInspection['is_verified'] ? true : null,
            'warning_message' => null,
        ];
    }
}
