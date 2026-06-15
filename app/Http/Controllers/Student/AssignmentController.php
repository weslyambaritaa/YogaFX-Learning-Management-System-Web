<?php

namespace App\Http\Controllers\Student;

use App\Events\EmailNotifications\AssignmentReviewRequested;
use App\Http\Controllers\Concerns\BuildsProtectedMediaUrls;
use App\Http\Controllers\Controller;
use App\Http\Requests\Student\AssignmentSubmissionRequest;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Services\BunnyStorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AssignmentController extends Controller
{
    use BuildsProtectedMediaUrls;

    public function __construct(
        private readonly BunnyStorageService $bunnyStorage,
    ) {
    }

    public function show(Request $request, Assignment $assignment): Response
    {
        $user = $request->user();
        $assignment->load('module.accessTiers');

        abort_unless(
            $user
            && $user->access_tier_id !== null
            && $assignment->status === Assignment::STATUS_LIVE
            && $assignment->module
            && $assignment->module->accessTiers()->where('access_tiers.id', $user->access_tier_id)->exists(),
            403,
        );

        $submission = AssignmentSubmission::query()
            ->where('assignment_id', $assignment->id)
            ->where('user_id', $user->id)
            ->latest('submitted_at')
            ->latest('id')
            ->first();

        return Inertia::render('Student/Assignments/Show', [
            'assignment' => [
                'id' => $assignment->id,
                'title' => $assignment->title,
                'description' => $assignment->description,
                'sort_order' => $assignment->sort_order,
                'status' => $assignment->status,
                'is_required' => $assignment->is_required,
                'module' => [
                    'id' => $assignment->module?->id,
                    'title' => $assignment->module?->title,
                    'url_slug' => $assignment->module?->url_slug,
                    'url' => $assignment->module?->url_slug
                        ? route('modules.show', $assignment->module->url_slug)
                        : null,
                ],
                'submission' => $submission ? [
                    'id' => $submission->id,
                    'status' => $submission->assignment_status,
                    'feedback' => $submission->assignment_feedback,
                    'submitted_at' => $submission->submitted_at?->format('Y-m-d H:i'),
                    'reviewed_at' => $submission->reviewed_at?->format('Y-m-d H:i'),
                    'video_url' => $this->protectedMediaUrl(
                        'assignment-submission',
                        $submission->id,
                        'assignment_video',
                        $submission->assignment_video,
                        versionSeed: $submission->updated_at,
                    ),
                ] : null,
            ],
            'uploadConstraints' => [
                'video_max_size_bytes' => \App\Support\UploadConstraints::ASSIGNMENT_VIDEO_MAX_FILE_SIZE_KB * 1024,
                'video_max_size_label' => \App\Support\UploadConstraints::labelFromMb(\App\Support\UploadConstraints::ASSIGNMENT_VIDEO_MAX_FILE_SIZE_MB),
            ],
            'status' => session('status'),
        ]);
    }

    public function store(AssignmentSubmissionRequest $request, Assignment $assignment): RedirectResponse
    {
        $user = $request->user();
        $assignment->load('module.accessTiers');

        abort_unless(
            $user
            && $user->access_tier_id !== null
            && $assignment->status === Assignment::STATUS_LIVE
            && $assignment->module
            && $assignment->module->accessTiers()->where('access_tiers.id', $user->access_tier_id)->exists(),
            403,
        );

        $existingSubmission = AssignmentSubmission::query()
            ->where('assignment_id', $assignment->id)
            ->where('user_id', $user->id)
            ->latest('submitted_at')
            ->latest('id')
            ->first();

        $newVideoPath = $this->bunnyStorage->upload(
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

        return redirect()
            ->route('assignments.show', $assignment)
            ->with('status', 'assignment-submission-saved');
    }
}
