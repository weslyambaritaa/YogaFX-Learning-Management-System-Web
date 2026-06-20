<?php

namespace App\Services\Mobile\V1;

use App\Events\EmailNotifications\AssignmentReviewRequested;
use App\Http\Controllers\Concerns\BuildsProtectedMediaUrls;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\User;
use App\Services\BunnyStorageService;
use App\Services\StudentLearningPathService;
use App\Support\UploadConstraints;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class StudentAssignmentApiService
{
    use BuildsProtectedMediaUrls;

    private const SUPPORTED_VIDEO_MIME_TYPES = [
        'video/mp4',
        'video/quicktime',
        'video/webm',
        'video/x-msvideo',
        'video/x-m4v',
        'application/octet-stream',
    ];

    public function __construct(
        private readonly BunnyStorageService $bunnyStorageService,
        private readonly StudentModuleApiService $studentModuleApiService,
        private readonly StudentLearningPathService $studentLearningPathService,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function detailForUser(User $user, Assignment $assignment): ?array
    {
        $context = $this->assignmentContextForUser($user, $assignment);

        if (! $context) {
            return null;
        }

        if (($context['is_locked'] ?? false) === true) {
            return [
                'id' => $assignment->id,
                'title' => $assignment->title,
                'is_locked' => true,
                'lock_reason' => $context['lock_reason'] ?? 'Complete the previous module requirements before opening this assignment.',
            ];
        }

        /** @var AssignmentSubmission|null $submission */
        $submission = $context['submission'];

        return [
            'id' => $assignment->id,
            'title' => $assignment->title,
            'description' => $assignment->description,
            'sort_order' => $assignment->sort_order,
            'status' => $assignment->status,
            'is_required' => (bool) $assignment->is_required,
            'is_locked' => false,
            'lock_reason' => null,
            'can_submit' => true,
            'module' => [
                'id' => $assignment->module?->id,
                'title' => $assignment->module?->title,
                'slug' => $assignment->module?->url_slug,
                'status' => $context['module_status'],
            ],
            'submission' => $this->submissionPayload($submission),
            'upload_constraints' => [
                'video_max_size_bytes' => UploadConstraints::ASSIGNMENT_VIDEO_MAX_FILE_SIZE_KB * 1024,
                'video_max_size_label' => UploadConstraints::labelFromMb(UploadConstraints::ASSIGNMENT_VIDEO_MAX_FILE_SIZE_MB),
                'accepted_extensions' => ['mp4', 'mov', 'webm', 'avi', 'm4v'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function submitForUser(Request $request, User $user, Assignment $assignment): ?array
    {
        $validator = Validator::make($request->all(), [
            'video' => [
                'required',
                'file',
                'max:'.UploadConstraints::ASSIGNMENT_VIDEO_MAX_FILE_SIZE_KB,
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! $value instanceof UploadedFile) {
                        return;
                    }

                    $extension = strtolower((string) $value->getClientOriginalExtension());

                    if (! in_array($extension, ['mp4', 'mov', 'webm', 'avi', 'm4v'], true)) {
                        $fail('Assignment video must use a supported video extension such as MP4, MOV, WEBM, AVI, or M4V.');

                        return;
                    }

                    $mimeType = $value->getMimeType() ?: $value->getClientMimeType();

                    if ($mimeType && (in_array($mimeType, self::SUPPORTED_VIDEO_MIME_TYPES, true) || str_starts_with($mimeType, 'video/'))) {
                        return;
                    }

                    $fail('Assignment video must be uploaded as a supported video file.');
                },
            ],
        ], [
            'video.max' => 'The assignment video must not be larger than '.UploadConstraints::labelFromMb(UploadConstraints::ASSIGNMENT_VIDEO_MAX_FILE_SIZE_MB).'.',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $context = $this->assignmentContextForUser($user, $assignment);

        if (! $context) {
            return null;
        }

        if (($context['is_locked'] ?? false) === true) {
            return [
                'id' => $assignment->id,
                'title' => $assignment->title,
                'is_locked' => true,
                'lock_reason' => $context['lock_reason'] ?? 'Complete the previous module requirements before opening this assignment.',
            ];
        }

        /** @var AssignmentSubmission|null $existingSubmission */
        $existingSubmission = $context['submission'];

        $newVideoPath = $this->bunnyStorageService->upload(
            $request->file('video'),
            'assignments/videos',
            $existingSubmission?->assignment_video,
        );

        $submission = $existingSubmission ?? new AssignmentSubmission([
            'assignment_id' => $assignment->id,
            'user_id' => $user->id,
        ]);

        $submission->assignment_id = $assignment->id;
        $submission->user_id = $user->id;
        $submission->assignment_type = Str::snake($assignment->title);
        $submission->assignment_video = $newVideoPath;
        $submission->assignment_status = AssignmentSubmission::STATUS_SUBMITTED;
        $submission->assignment_feedback = null;
        $submission->submitted_at = now();
        $submission->graded_at = null;
        $submission->reviewed_at = null;
        $submission->reviewed_by = null;
        $submission->save();

        event(new AssignmentReviewRequested([
            'user_name' => $user->name,
            'user_email' => $user->email,
            'assignment_type' => $assignment->title,
            'admin_email' => config('mail.from.address'),
        ], 'assignment_submission', $submission->id));

        return [
            'assignment_id' => $assignment->id,
            'submission' => $this->submissionPayload($submission),
        ];
    }

    /**
     * @return array{submission: AssignmentSubmission|null, module_status: string|null, is_locked: bool, lock_reason: string|null}|null
     */
    private function assignmentContextForUser(User $user, Assignment $assignment): ?array
    {
        $assignment->loadMissing('module.accessTiers');

        if (
            $user->access_tier_id === null
            || ! $this->studentLearningPathService->assignmentFlowAccessibleForStudent($user)
            || $assignment->status !== Assignment::STATUS_LIVE
            || ! $assignment->module
            || ! $assignment->module->accessTiers()->where('access_tiers.id', $user->access_tier_id)->exists()
        ) {
            return null;
        }

        $moduleDetail = $this->studentModuleApiService->moduleDetailForUser($user, $assignment->module_id);

        if (! $moduleDetail) {
            return null;
        }

        $submission = AssignmentSubmission::query()
            ->where('assignment_id', $assignment->id)
            ->where('user_id', $user->id)
            ->latest('submitted_at')
            ->latest('id')
            ->first();

        if (($moduleDetail['is_visible'] ?? true) === false || ($moduleDetail['status'] ?? null) === 'locked') {
            return [
                'submission' => $submission,
                'module_status' => $moduleDetail['status'] ?? null,
                'is_locked' => true,
                'lock_reason' => $moduleDetail['lock_reason'] ?? 'Complete the previous module requirements before opening this assignment.',
            ];
        }

        return [
            'submission' => $submission,
            'module_status' => $moduleDetail['status'] ?? null,
            'is_locked' => false,
            'lock_reason' => null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function submissionPayload(?AssignmentSubmission $submission): ?array
    {
        if (! $submission) {
            return null;
        }

        return [
            'id' => $submission->id,
            'status' => $submission->assignment_status,
            'feedback' => $submission->assignment_feedback,
            'submitted_at' => $submission->submitted_at?->toIso8601String(),
            'reviewed_at' => $submission->reviewed_at?->toIso8601String(),
            'video_url' => $this->protectedMediaUrl(
                'assignment-submission',
                $submission->id,
                'assignment_video',
                $submission->assignment_video,
                versionSeed: $submission->updated_at,
            ),
        ];
    }
}
