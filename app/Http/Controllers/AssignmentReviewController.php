<?php

namespace App\Http\Controllers;

use App\Events\EmailNotifications\AssignmentApproved;
use App\Events\EmailNotifications\AssignmentRejected;
use App\Models\AssignmentSubmission;
use App\Services\BunnyStorageService;
use App\Support\BunnyAssetPath;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AssignmentReviewController extends Controller
{
    public function __construct(
        private readonly BunnyStorageService $bunnyStorageService,
    ) {
    }

    public function show(string $reviewToken): Response
    {
        $submission = $this->resolveSubmission($reviewToken);

        $student = $submission->user;
        $assignment = $submission->assignment;
        $module = $assignment?->module;

        return Inertia::render('Public/AssignmentReview', [
            'review' => [
                'token' => $reviewToken,

                'student' => [
                    'name' => $student?->name ?? 'Student',
                    'email' => $student?->email ?? '-',
                    'profile_photo_url' => filled($student?->profile_photo)
                        ? route('assignment-review.profile-photo', [
                            'reviewToken' => $reviewToken,
                        ])
                        : null,
                    'initials' => $this->initialsFor(
                        $student?->name ?? 'Student'
                    ),
                ],

                'assignment' => [
                    'title' => $submission->title(),
                    'module_title' => $module?->title ?? '-',
                ],

                'status' => $submission->assignment_status,
                'feedback' => $submission->assignment_feedback,

                'submitted_at' => $submission->submitted_at
                    ?->format('d M Y, H:i'),

                'reviewed_at' => $submission->reviewed_at
                    ?->format('d M Y, H:i'),

                'video_url' => filled($submission->assignment_video)
                    ? route('assignment-review.video', [
                        'reviewToken' => $reviewToken,
                    ])
                    : null,

                'update_url' => route('assignment-review.update', [
                    'reviewToken' => $reviewToken,
                ]),

                'status_options' => collect(AssignmentSubmission::STATUSES)
                    ->map(fn (string $status) => [
                        'value' => $status,
                        'label' => $this->statusLabel($status),
                    ])
                    ->values(),
            ],

            'status' => session('status'),
        ]);
    }

    public function update(
        Request $request,
        string $reviewToken,
    ): RedirectResponse {
        $submission = $this->resolveSubmission($reviewToken);

        $data = $request->validate([
            'assignment_status' => [
                'required',
                Rule::in(AssignmentSubmission::STATUSES),
            ],
            'assignment_feedback' => [
                'nullable',
                'string',
                'max:5000',
            ],
        ]);

        $submission->assignment_status = $data['assignment_status'];
        $submission->assignment_feedback =
            $data['assignment_feedback'] ?? null;

        $isFinalDecision = in_array(
            $submission->assignment_status,
            [
                AssignmentSubmission::STATUS_APPROVED,
                AssignmentSubmission::STATUS_REJECTED,
            ],
            true,
        );

        $submission->graded_at = $isFinalDecision ? now() : null;
        $submission->reviewed_at = $isFinalDecision ? now() : null;

        /*
         * Halaman review ini memang tidak menggunakan authentication.
         * Karena itu reviewed_by tidak boleh diisi dengan user ID palsu.
         */
        $submission->reviewed_by = null;

        $submission->save();

        $student = $submission->user;

        $emailPayload = [
            'user_name' => $student?->name ?? 'Student',
            'user_email' => $student?->email,
            'assignment_type' => $submission->emailTypeLabel(),
            'feedback' => $submission->assignment_feedback,
            'admin_email' => config('mail.from.address'),
            'dashboard_url' => route('student.dashboard'),
        ];

        if (
            $submission->assignment_status
            === AssignmentSubmission::STATUS_APPROVED
        ) {
            event(new AssignmentApproved(
                $emailPayload,
                'assignment_submission',
                $submission->id,
            ));
        }

        if (
            $submission->assignment_status
            === AssignmentSubmission::STATUS_REJECTED
        ) {
            event(new AssignmentRejected(
                $emailPayload,
                'assignment_submission',
                $submission->id,
            ));
        }

        return redirect()
            ->route('assignment-review.show', [
                'reviewToken' => $reviewToken,
            ])
            ->with('status', 'assignment-review-saved');
    }

    public function video(string $reviewToken)
    {
        $submission = $this->resolveSubmission($reviewToken);

        return $this->serveMediaPath(
            $submission->assignment_video
        );
    }

    public function profilePhoto(string $reviewToken)
    {
        $submission = $this->resolveSubmission($reviewToken);

        return $this->serveMediaPath(
            $submission->user?->profile_photo
        );
    }

    private function resolveSubmission(
        string $reviewToken,
    ): AssignmentSubmission {
        return AssignmentSubmission::query()
            ->with([
                'user',
                'assignment.module',
            ])
            ->where('review_token', $reviewToken)
            ->firstOrFail();
    }

    private function serveMediaPath(?string $path)
    {
        abort_unless(filled($path), 404);

        if (BunnyAssetPath::isBunnyPath($path)) {
            $url = $this->bunnyStorageService->url($path);

            abort_unless(filled($url), 404);

            return redirect()->away($url);
        }

        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return redirect()->away($path);
        }

        abort_unless(
            Storage::disk('local')->exists($path),
            404,
        );

        return Storage::disk('local')->response($path);
    }

    private function initialsFor(string $name): string
    {
        $initials = collect(
            preg_split('/\s+/', trim($name)) ?: []
        )
            ->filter()
            ->take(2)
            ->map(
                fn (string $part) =>
                    str($part)->substr(0, 1)->upper()->value()
            )
            ->implode('');

        return $initials !== '' ? $initials : 'ST';
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            AssignmentSubmission::STATUS_SUBMITTED => 'Submitted',
            AssignmentSubmission::STATUS_UNDER_REVIEW => 'Under Review',
            AssignmentSubmission::STATUS_PENDING_REVIEW => 'Pending Review',
            AssignmentSubmission::STATUS_APPROVED => 'Approved',
            AssignmentSubmission::STATUS_REJECTED => 'Needs Resubmission',
            default => str($status)
                ->replace('_', ' ')
                ->title()
                ->value(),
        };
    }
}