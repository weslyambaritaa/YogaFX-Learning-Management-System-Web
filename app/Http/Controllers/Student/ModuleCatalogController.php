<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Concerns\BuildsProtectedMediaUrls;
use App\Http\Controllers\Controller;
use App\Models\AccessTier;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\AssessmentAttempt;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Ebook;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Module;
use App\Services\BunnyStreamService;
use App\Services\Certificates\CertificateEligibilityService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ModuleCatalogController extends Controller
{
    use BuildsProtectedMediaUrls;

    private const CERTIFICATE_DOWNLOAD_SLUG = 'certificate-download';
    public function __construct(
        private readonly BunnyStreamService $bunnyStreamService,
        private readonly CertificateEligibilityService $certificateEligibilityService,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $accessTierId = $user?->access_tier_id;

        $modules = $this->accessibleModulesWithLessons($accessTierId);

        $lessonProgressMap = $this->lessonProgressMap(
            $user?->id,
            $modules->flatMap(fn (Module $module) => $module->lessons->pluck('id')),
        );
        $completedAssessmentIds = $this->completedAssessmentIds(
            $user?->id,
            $modules->flatMap(fn (Module $module) => $module->lessons->pluck('assessment_id'))->filter(),
        );
        $lessonUnlockMap = $this->lessonUnlockMap($user?->id, $modules, $lessonProgressMap);
        $assignmentSubmissionMap = $this->assignmentSubmissionMap(
            $user?->id,
            $modules->flatMap(fn (Module $module) => $module->assignments->pluck('id')),
        );
        $moduleAccessMap = $this->moduleAccessMap(
            $user,
            $modules,
            $lessonProgressMap,
            $completedAssessmentIds,
            $assignmentSubmissionMap,
        );
        $activeLessonId = $this->latestProgressLessonId($user?->id, $lessonProgressMap);

        return Inertia::render('Student/Modules/Index', [
            'modules' => $modules->map(function (Module $module) use ($activeLessonId, $lessonProgressMap, $completedAssessmentIds, $moduleAccessMap) {
                $moduleAccess = $moduleAccessMap->get($module->id, [
                    'is_visible' => false,
                    'status' => 'locked',
                    'is_complete' => false,
                ]);
                $totalLessons = $module->lessons->count();
                $totalAssignments = $module->assignments->count();
                $completedLessons = $module->lessons->filter(function (Lesson $lesson) use ($lessonProgressMap, $completedAssessmentIds) {
                    return $this->isLessonFullyComplete(
                        $lesson,
                        $lessonProgressMap->get($lesson->id),
                        $completedAssessmentIds,
                    );
                })->count();
                $isActive = $module->lessons->contains(fn (Lesson $lesson) => $lesson->id === $activeLessonId);
                $status = $moduleAccess['status'] === 'available' && $isActive
                    ? 'active'
                    : $moduleAccess['status'];

                return [
                    'id' => $module->id,
                    'title' => $module->title,
                    'description' => $moduleAccess['description'] ?? $module->description,
                    'url_slug' => $module->url_slug,
                    'url' => ($moduleAccess['is_visible'] ?? false) ? route('modules.show', $module->url_slug) : null,
                    'sort_order' => $module->sort_order,
                    'lesson_count' => $totalLessons,
                    'assignments_count' => $totalAssignments,
                    'certificate_enabled' => (bool) $module->certificate_enabled,
                    'ebook_enabled' => (bool) $module->ebook_enabled,
                    'video_lecturer_enabled' => (bool) $module->video_lecturer_enabled,
                    'completed_lessons' => $completedLessons,
                    'progress_percentage' => $totalLessons > 0
                        ? (int) round(($completedLessons / $totalLessons) * 100)
                        : (($moduleAccess['is_complete'] ?? false) ? 100 : 0),
                    'status' => $status,
                    'thumbnail_url' => $this->protectedMediaUrl(
                        'module',
                        $module->id,
                        'thumbnail',
                        $module->thumbnail,
                        versionSeed: $module->updated_at,
                    ),
                ];
            }),
        ]);
    }

    public function show(Request $request, Module $module): Response
    {
        $user = $request->user();
        $accessTierId = $user?->access_tier_id;
        abort_unless(
            $user
            && $module->accessTiers()->where('access_tiers.id', $accessTierId)->exists(),
            403,
        );

        $modules = $this->accessibleModulesWithLessons($accessTierId);
        $currentModule = $modules->firstWhere('id', $module->id);
        abort_unless($currentModule, 404);

        $lessonProgressMap = $this->lessonProgressMap(
            $user?->id,
            $modules->flatMap(fn (Module $item) => $item->lessons->pluck('id')),
        );
        $completedAssessmentIds = $this->completedAssessmentIds(
            $user?->id,
            $modules->flatMap(fn (Module $item) => $item->lessons->pluck('assessment_id'))->filter(),
        );
        $assignmentSubmissionMap = $this->assignmentSubmissionMap(
            $user?->id,
            $modules->flatMap(fn (Module $item) => $item->assignments->pluck('id')),
        );
        $moduleAccessMap = $this->moduleAccessMap(
            $user,
            $modules,
            $lessonProgressMap,
            $completedAssessmentIds,
            $assignmentSubmissionMap,
        );
        $currentModuleAccess = $moduleAccessMap->get($currentModule->id);

        abort_unless((bool) ($currentModuleAccess['is_visible'] ?? false), 403);

        if ($this->isCertificateDownloadModule($currentModule)) {
            return Inertia::render('Student/Certificates/Show', [
                'module' => [
                    'id' => $currentModule->id,
                    'title' => $currentModule->title,
                    'description' => $currentModule->description,
                    'url_slug' => $currentModule->url_slug,
                    'sort_order' => $currentModule->sort_order,
                    'thumbnail_url' => $this->protectedMediaUrl(
                        'module',
                        $currentModule->id,
                        'thumbnail',
                        $currentModule->thumbnail,
                        versionSeed: $currentModule->updated_at,
                    ),
                ],
                'certificate' => $currentModuleAccess['certificate_state'] ?? [],
            ]);
        }

        $lessons = $currentModule->lessons;
        $lessonUnlockMap = $this->lessonUnlockMap($user?->id, $modules, $lessonProgressMap);
        $activeLessonId = $this->latestProgressLessonId($user?->id, $lessonProgressMap);
        $completedLessons = $lessons->filter(fn (Lesson $lesson) => $this->isLessonFullyComplete(
            $lesson,
            $lessonProgressMap->get($lesson->id),
            $completedAssessmentIds,
        ))->count();

        return Inertia::render('Student/Modules/Show', [
            'module' => [
                'id' => $module->id,
                'title' => $module->title,
                'description' => $module->description,
                'url_slug' => $module->url_slug,
                'sort_order' => $module->sort_order,
                'lesson_count' => $lessons->count(),
                'completed_lessons' => $completedLessons,
                'progress_percentage' => $lessons->count() > 0
                    ? (int) round(($completedLessons / $lessons->count()) * 100)
                    : (($currentModuleAccess['is_complete'] ?? false) ? 100 : 0),
                'certificate_enabled' => (bool) $module->certificate_enabled,
                'ebook_enabled' => (bool) $module->ebook_enabled,
                'video_lecturer_enabled' => (bool) $module->video_lecturer_enabled,
                'thumbnail_url' => $this->protectedMediaUrl(
                    'module',
                    $module->id,
                    'thumbnail',
                    $module->thumbnail,
                    versionSeed: $module->updated_at,
                ),
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
                        'thumbnail_url' => $this->lessonThumbnailUrl($lesson, $module),
                        'url' => ($lessonUnlockMap->get($lesson->id)['is_unlocked'] ?? false)
                            ? route('lessons.show', $lesson)
                            : null,
                    ]),
                'assignments' => $currentModule->assignments
                    ->map(function ($assignment) use ($assignmentSubmissionMap) {
                        $submission = $assignmentSubmissionMap->get($assignment->id);

                        return [
                            'id' => $assignment->id,
                            'title' => $assignment->title,
                            'description' => $assignment->description,
                            'sort_order' => $assignment->sort_order,
                            'status' => $assignment->status,
                            'is_required' => $assignment->is_required,
                            'submission_status' => $submission?->assignment_status,
                            'submission_feedback' => $submission?->assignment_feedback,
                            'submitted_at' => $submission?->submitted_at?->format('Y-m-d H:i'),
                            'url' => route('assignments.show', $assignment),
                        ];
                    })
                    ->values(),
                'ebooks' => $module->ebook_enabled
                    ? $this->ebooksForStudent($user?->access_tier_id)
                    : [],
                'video_lecturers' => $module->video_lecturer_enabled
                    ? $this->videoLecturersForStudent($user?->access_tier_id)
                    : [],
                'certificates' => $module->certificate_enabled
                    ? $this->certificateEligibilityService
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
                            'generated_at' => optional($certificate->generated_at)->format('Y-m-d H:i'),
                            'download_url' => route('student.certificates.download', $certificate),
                        ])
                        ->all()
                    : [],
            ],
        ]);
    }

    private function accessibleModulesWithLessons(?int $accessTierId): Collection
    {
        return Module::query()
            ->whereHas('accessTiers', fn ($query) => $query->where('access_tiers.id', $accessTierId))
            ->with([
                'lessons' => fn ($query) => $query
                    ->select(['id', 'module_id', 'title', 'sort_order', 'assessment_id', 'lesson_video_id', 'workbook', 'audio_url', 'content', 'thumbnail'])
                    ->with(['assessment:id,status,is_active'])
                    ->whereHas('accessTiers', fn ($lessonQuery) => $lessonQuery->where('access_tiers.id', $accessTierId))
                    ->orderBy('sort_order')
                    ->orderBy('title'),
                'assignments' => fn ($query) => $query
                    ->where('status', \App\Models\Assignment::STATUS_LIVE)
                    ->orderBy('sort_order')
                    ->orderBy('title'),
            ])
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get();
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

    private function ebooksForStudent(?int $accessTierId): array
    {
        return Ebook::query()
            ->whereHas('accessTiers', fn ($query) => $query->where('access_tiers.id', $accessTierId))
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get()
            ->map(function (Ebook $ebook) {
                $downloadUrl = $this->protectedMediaUrl(
                    'ebook',
                    $ebook->id,
                    'file',
                    $ebook->file,
                    download: true,
                    versionSeed: $ebook->updated_at,
                );
                $mimeType = $ebook->file
                    ? Storage::disk('local')->mimeType($ebook->file)
                    : null;

                return [
                    'id' => $ebook->id,
                    'title' => $ebook->title,
                    'sort_order' => $ebook->sort_order,
                    'file_name' => basename((string) $ebook->file),
                    'preview_url' => route('ebooks.preview', $ebook),
                    'download_url' => $downloadUrl,
                    'preview_supported' => str($ebook->file)->lower()->endsWith('.pdf')
                        || $mimeType === 'application/pdf',
                ];
            })
            ->values()
            ->all();
    }

    private function videoLecturersForStudent(?int $accessTierId): array
    {
        return Course::query()
            ->whereHas('accessTiers', fn ($query) => $query->where('access_tiers.id', $accessTierId))
            ->orderBy('title')
            ->get()
            ->values()
            ->map(function (Course $course, int $index) {
                $videoState = $this->videoStateForCourse($course);

                return [
                    'id' => $course->id,
                    'title' => $course->title,
                    'url_slug' => $course->url_slug,
                    'description' => $course->description,
                    'index' => $index + 1,
                    'video' => $videoState,
                    'thumbnail_url' => $this->protectedMediaUrl(
                        'course',
                        $course->id,
                        'thumbnail',
                        $course->thumbnail,
                        versionSeed: $course->updated_at,
                    ) ?: $this->bunnyStreamService->thumbnailUrl($course->video),
                ];
            })
            ->all();
    }

    private function moduleAccessMap(
        $user,
        Collection $modules,
        Collection $lessonProgressMap,
        Collection $completedAssessmentIds,
        Collection $assignmentSubmissionMap,
    ): Collection {
        $accessMap = collect();
        $allPreviousModulesComplete = true;

        foreach ($modules as $module) {
            if ($this->isCertificateDownloadModule($module)) {
                $certificateState = $this->certificateAccessState(
                    $user,
                    $modules,
                    $lessonProgressMap,
                    $completedAssessmentIds,
                );

                $accessMap->put($module->id, [
                    'is_visible' => (bool) ($certificateState['is_visible'] ?? false),
                    'status' => $certificateState['module_status'] ?? 'locked',
                    'description' => $certificateState['module_description'] ?? $module->description,
                    'is_complete' => ($certificateState['state'] ?? null) === 'download_available',
                    'certificate_state' => $certificateState,
                ]);

                continue;
            }

            $isComplete = $this->isModuleFullyComplete(
                $module,
                $lessonProgressMap,
                $completedAssessmentIds,
                $assignmentSubmissionMap,
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

    private function lessonUnlockMap(?int $userId, Collection $modules, Collection $lessonProgressMap): Collection
    {
        $orderedLessons = $modules->flatMap(fn (Module $module) => $module->lessons)->values();
        $completedAssessmentIds = $this->completedAssessmentIds(
            $userId,
            $orderedLessons->pluck('assessment_id')->filter(),
        );
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

    /**
     * @return array{
     *     video_id: string|null,
     *     hls_url: string|null,
     *     is_ready: bool,
     *     is_configured: bool,
     *     is_valid_id: bool,
     *     is_found_in_library: bool|null,
     *     warning_message: string|null
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

        if (! \Illuminate\Support\Str::isUuid($videoId)) {
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

    private function isModuleFullyComplete(
        Module $module,
        Collection $lessonProgressMap,
        Collection $completedAssessmentIds,
        Collection $assignmentSubmissionMap,
    ): bool {
        $liveAssignments = $module->assignments->where('status', Assignment::STATUS_LIVE);

        if ($module->lessons->isEmpty() && $liveAssignments->isEmpty()) {
            return (bool) $module->ebook_enabled;
        }

        $hasTrackableContent = $module->lessons->isNotEmpty() || $liveAssignments->isNotEmpty();

        if (! $hasTrackableContent) {
            return false;
        }

        $allLessonsComplete = $module->lessons->every(
            fn (Lesson $lesson) => $this->isLessonFullyComplete(
                $lesson,
                $lessonProgressMap->get($lesson->id),
                $completedAssessmentIds,
            ),
        );

        $allRequiredAssignmentsComplete = $liveAssignments
            ->every(fn (Assignment $assignment) => $this->isAssignmentComplete(
                $assignmentSubmissionMap->get($assignment->id),
            ));

        return $allLessonsComplete && $allRequiredAssignmentsComplete;
    }

    private function isAssignmentComplete(?AssignmentSubmission $submission): bool
    {
        return $submission?->assignment_status === AssignmentSubmission::STATUS_APPROVED;
    }

    private function isCertificateDownloadModule(Module $module): bool
    {
        return $module->url_slug === self::CERTIFICATE_DOWNLOAD_SLUG;
    }

    private function certificateAccessState(
        $user,
        Collection $modules,
        Collection $lessonProgressMap,
        Collection $completedAssessmentIds,
    ): array {
        $tier = $user?->accessTier;
        $summary = $user ? $this->certificateEligibilityService->summaryForStudent($user) : null;
        $latestCertificate = $user
            ? Certificate::query()
                ->where('user_id', $user->id)
                ->latest('generated_at')
                ->latest('id')
                ->first()
            : null;
        $availableTypes = collect($summary['available_types'] ?? []);
        $eligibleTier = $user && $user->access_tier_id !== null && $availableTypes->isNotEmpty();
        $learningModules = $modules
            ->reject(fn (Module $module) => $this->isCertificateDownloadModule($module))
            ->filter(fn (Module $module) => $module->lessons->isNotEmpty())
            ->values();
        $currentPathCompleted = $learningModules->isNotEmpty()
            && $learningModules->every(fn (Module $module) => $module->lessons->every(
                fn (Lesson $lesson) => $this->isLessonFullyComplete(
                    $lesson,
                    $lessonProgressMap->get($lesson->id),
                    $completedAssessmentIds,
                ),
            ));
        $learningEligible = (bool) ($summary['learning_eligible'] ?? false);
        $hasCertificate = (bool) $latestCertificate;
        $isVisible = $eligibleTier && ($learningEligible || $hasCertificate);
        $state = ! $eligibleTier
            ? 'not_available'
            : ($hasCertificate ? 'download_available' : ($learningEligible ? 'ready' : 'locked'));

        return [
            'state' => $state,
            'is_visible' => $isVisible,
            'module_status' => $hasCertificate ? 'completed' : ($learningEligible ? 'available' : 'locked'),
            'module_description' => $hasCertificate
                ? 'Your certificate is ready. Open this module to review and download your latest YogaFX certificate.'
                : ($learningEligible
                    ? 'Your learning journey is complete and this certificate module is now open while certificate generation is being finalized.'
                    : 'Complete your full YogaFX learning journey to unlock certificate access.'),
            'title' => $hasCertificate
                ? 'Your latest certificate is ready to download.'
                : ($learningEligible
                    ? 'Your certificate milestone is ready from the learning side.'
                    : 'Certificate access is not unlocked yet.'),
            'description' => $hasCertificate
                ? 'This module now acts as your student certificate area. Download the latest certificate record generated for your account.'
                : ($learningEligible
                    ? 'You have completed the accessible learning path for your current tier. If the certificate file has not been generated yet, please wait for the YogaFX team to finalize it.'
                    : 'Certificate access opens after the required YogaFX journey has been completed.'),
            'eligibility_label' => $eligibleTier
                ? 'Certificate included in '.($tier?->name ?? 'your current tier')
                : 'Certificate not available in this tier',
            'support_note' => $hasCertificate
                ? 'The latest available certificate record is surfaced here so you do not need a separate student certificate menu.'
                : 'This page opens as soon as your learning path reaches certificate readiness, even if the final file is still waiting to be generated.',
            'requirements' => $summary['requirements'] ?? [],
            'learning_eligible' => $learningEligible,
            'has_required_name' => (bool) ($summary['has_required_name'] ?? false),
            'eligibility_message' => $summary['message'] ?? null,
            'latest_certificate' => $latestCertificate ? [
                'id' => $latestCertificate->id,
                'type_label' => $latestCertificate->typeLabel(),
                'version' => $latestCertificate->version,
                'generated_at' => optional($latestCertificate->generated_at)->format('Y-m-d H:i'),
                'download_url' => route('student.certificates.download', $latestCertificate),
            ] : null,
            'cta_label' => $hasCertificate ? 'Download Latest Certificate' : 'Browse Modules',
            'cta_url' => $hasCertificate
                ? route('student.certificates.download', $latestCertificate)
                : route('modules.index'),
            'cta_kind' => $hasCertificate ? 'download' : 'link',
        ];
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

    private function lessonThumbnailUrl(Lesson $lesson, Module $module): ?string
    {
        return $this->protectedMediaUrl(
            'lesson',
            $lesson->id,
            'thumbnail',
            $lesson->thumbnail,
            versionSeed: $lesson->updated_at,
        ) ?: $this->bunnyStreamService->thumbnailUrl($lesson->lesson_video_id)
            ?: $this->protectedMediaUrl(
                'module',
                $module->id,
                'thumbnail',
                $module->thumbnail,
                versionSeed: $module->updated_at,
            );
    }
}
