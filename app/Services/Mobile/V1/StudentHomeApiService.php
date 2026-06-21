<?php

namespace App\Services\Mobile\V1;

use App\Http\Controllers\Student\HomeController;
use App\Models\Lesson;
use App\Models\Module;
use App\Services\Mobile\V1\Concerns\BuildsMobileSignedContentImageUrls;
use App\Services\BunnyStreamService;
use App\Services\BunnyStorageService;
use App\Services\Certificates\CertificateEligibilityService;
use App\Services\CertificateDownloadTrackingService; // 1. Pastikan class ini di-import
use App\Services\StudentLearningPathService;
use App\Services\StudentSessionTrackingService;
use Illuminate\Http\Request;

class StudentHomeApiService extends HomeController
{
    use BuildsMobileSignedContentImageUrls;

    public function __construct(
        private readonly StudentSessionTrackingService $studentSessionTrackingService,
        private readonly BunnyStreamService $bunnyStreamService,
        StudentSessionTrackingService $sessionTrackingService,
        CertificateEligibilityService $certificateEligibilityService,
        BunnyStorageService $bunnyStorage,
        CertificateDownloadTrackingService $certificateDownloadTrackingService,
        StudentLearningPathService $studentLearningPathService,
    ) {
        parent::__construct(
            $sessionTrackingService,
            $certificateEligibilityService,
            $bunnyStorage,
            $certificateDownloadTrackingService,
            $studentLearningPathService,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function payloadForUser(Request $request): array
    {
        $user = $request->user();
        $displayName = trim((string) ($user?->first_name ?: $user?->name ?: 'Student'));
        $tier = $user?->accessTier;
        $availableModules = $this->availableModulesForStudent($user);
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

        return [
            'home_stage' => 12,
            'student_context' => [
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
            'access_time_summary' => $user
                ? $this->mobileAccessTimeSummary($user)
                : null,
            'continue_learning_section' => $continueLearning,
            'progress_summary_section' => $progressSummary,
            'next_step' => $nextStep,
            'sequential_awareness' => $sequentialAwareness,
            'available_modules_section' => $availableModulesSection,
            'assignment_milestone' => $assignmentMilestone,
            'certificate_milestone' => $certificateMilestone,
            'ebook_resources_section' => $ebookResourcesSection,
            'home_experience' => $homeExperience,
        ];
    }

    protected function moduleThumbnailUrl(Module $module): ?string
    {
        $user = request()->user();

        if (! $user) {
            return parent::moduleThumbnailUrl($module);
        }

        return $this->mobileSignedContentImageUrl(
            $user,
            'module',
            $module->id,
            'thumbnail',
            $module->thumbnail,
            $module->updated_at,
        );
    }

    protected function lessonThumbnailUrl($lesson, ?Module $module = null): ?string
    {
        $user = request()->user();

        if (! $user || ! $lesson instanceof Lesson) {
            return parent::lessonThumbnailUrl($lesson, $module);
        }

        return $this->mobileSignedContentImageUrl(
            $user,
            'lesson',
            $lesson->id,
            'thumbnail',
            $lesson->thumbnail,
            $lesson->updated_at,
        ) ?: $this->bunnyStreamService->thumbnailUrl($lesson->lesson_video_id)
            ?: ($module ? $this->moduleThumbnailUrl($module) : null);
    }

    /**
     * @return array<string, mixed>
     */
    private function mobileAccessTimeSummary($user): array
    {
        $summary = $this->studentSessionTrackingService->summaryForUser($user);

        return [
            ...$summary,
            'persisted_total_access_duration_seconds' => $summary['total_access_duration_seconds'],
            'total_access_duration_seconds' => $summary['running_total_access_duration_seconds'],
        ];
    }
}
