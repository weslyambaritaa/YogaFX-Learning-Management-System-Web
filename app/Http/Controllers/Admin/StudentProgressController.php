<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\StudentProgressActionMail;
use App\Models\AssignmentSubmission;
use App\Models\Certificate;
use App\Models\LessonProgress;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class StudentProgressController extends Controller
{
    public function show(Request $request, User $student): Response
    {
        $student = $this->resolveStudent($student);

        return Inertia::render('Admin/Students/Progress', [
            'student' => [
                'id' => $student->id,
                'name' => $student->name,
                'email' => $student->email,
                'access_tier' => $student->accessTier?->name,
                'access_tier_slug' => $student->accessTier?->slug,
            ],
            'activeTab' => $this->normalizeTab((string) $request->query('tab', 'completed-lessons')),
            'completedLessons' => LessonProgress::query()
                ->with(['lesson.module'])
                ->where('user_id', $student->id)
                ->where('is_done', true)
                ->orderByDesc('completed_at')
                ->get()
                ->map(fn (LessonProgress $progress) => [
                    'id' => $progress->id,
                    'lesson_title' => $progress->lesson?->title ?? 'Unknown lesson',
                    'module_title' => $progress->lesson?->module?->title ?? '-',
                    'completed_at' => optional($progress->completed_at)->format('Y-m-d H:i'),
                    'watch_progress' => (float) $progress->watch_progress,
                ]),
            'assignments' => AssignmentSubmission::query()
                ->where('user_id', $student->id)
                ->orderByDesc('submitted_at')
                ->orderByDesc('id')
                ->get()
                ->map(fn (AssignmentSubmission $assignment) => [
                    'id' => $assignment->id,
                    'title' => $assignment->title(),
                    'video' => $assignment->assignment_video,
                    'status' => $assignment->assignment_status,
                    'feedback' => $assignment->assignment_feedback,
                    'submitted_at' => optional($assignment->submitted_at)->format('Y-m-d H:i'),
                ]),
            'assignmentStatuses' => collect(AssignmentSubmission::STATUSES)
                ->map(fn (string $status) => [
                    'value' => $status,
                    'label' => str($status)->replace('_', ' ')->title()->value(),
                ])
                ->values(),
            'certificates' => Certificate::query()
                ->where('user_id', $student->id)
                ->latest('generated_at')
                ->latest('id')
                ->get()
                ->map(fn (Certificate $certificate) => [
                    'id' => $certificate->id,
                    'type' => $certificate->certificate_type,
                    'type_label' => $certificate->typeLabel(),
                    'version' => $certificate->version,
                    'generated_at' => optional($certificate->generated_at)->format('Y-m-d H:i'),
                    'download_url' => route('admin.students.progress.certificates.download', [
                        'student' => $student,
                        'certificate' => $certificate,
                    ]),
                ]),
            'certificateTypes' => collect(Certificate::TYPES)
                ->map(fn (string $label, string $value) => [
                    'value' => $value,
                    'label' => $label,
                ])
                ->values(),
            'certificateEligibility' => [
                'eligible' => $this->studentCanReceiveCertificates($student),
                'message' => $this->studentCanReceiveCertificates($student)
                    ? null
                    : 'This student is not eligible to receive a certificate based on the current access tier.',
            ],
            'status' => session('status'),
        ]);
    }

    public function resetLesson(User $student, LessonProgress $lessonProgress): RedirectResponse
    {
        $student = $this->resolveStudent($student);
        abort_unless($lessonProgress->user_id === $student->id, 404);

        $lessonProgress->update([
            'watch_progress' => 0,
            'is_workbook_downloaded' => false,
            'workbook_downloaded_at' => null,
            'video_completed_at' => null,
            'is_done' => false,
            'completed_at' => null,
        ]);

        return redirect()
            ->route('admin.students.progress.show', [
                'student' => $student,
                'tab' => 'completed-lessons',
            ])
            ->with('status', 'student-progress-lesson-reset');
    }

    public function updateAssignment(Request $request, User $student, AssignmentSubmission $assignmentSubmission): RedirectResponse
    {
        $student = $this->resolveStudent($student);
        abort_unless($assignmentSubmission->user_id === $student->id, 404);

        $data = $request->validate([
            'assignment_status' => ['required', 'in:'.implode(',', AssignmentSubmission::STATUSES)],
            'assignment_feedback' => ['nullable', 'string'],
        ]);

        $assignmentSubmission->fill($data);
        $assignmentSubmission->graded_at = in_array(
            $assignmentSubmission->assignment_status,
            [AssignmentSubmission::STATUS_APPROVED, AssignmentSubmission::STATUS_REJECTED],
            true,
        ) ? now() : null;
        $assignmentSubmission->save();

        return redirect()
            ->route('admin.students.progress.show', [
                'student' => $student,
                'tab' => 'assignment',
            ])
            ->with('status', 'student-progress-assignment-saved');
    }

    public function sendAssignmentEmail(User $student, AssignmentSubmission $assignmentSubmission): RedirectResponse
    {
        $student = $this->resolveStudent($student);
        abort_unless($assignmentSubmission->user_id === $student->id, 404);

        $this->sendEmail(
            $student,
            'Your YogaFX assignment update',
            'Assignment Update',
            [
                'Assignment: '.$assignmentSubmission->title(),
                'Status: '.str($assignmentSubmission->assignment_status)->replace('_', ' ')->title()->value(),
                $assignmentSubmission->assignment_feedback
                    ? 'Feedback: '.$assignmentSubmission->assignment_feedback
                    : 'No additional feedback was provided.',
            ],
        );

        return redirect()
            ->route('admin.students.progress.show', [
                'student' => $student,
                'tab' => 'assignment',
            ])
            ->with('status', 'student-progress-assignment-email-sent');
    }

    public function deleteAssignmentVideo(User $student, AssignmentSubmission $assignmentSubmission): RedirectResponse
    {
        $student = $this->resolveStudent($student);
        abort_unless($assignmentSubmission->user_id === $student->id, 404);

        $assignmentSubmission->update([
            'assignment_video' => null,
        ]);

        return redirect()
            ->route('admin.students.progress.show', [
                'student' => $student,
                'tab' => 'assignment',
            ])
            ->with('status', 'student-progress-assignment-video-deleted');
    }

    public function generateCertificate(Request $request, User $student): RedirectResponse
    {
        $student = $this->resolveStudent($student);
        abort_unless($this->studentCanReceiveCertificates($student), 422);

        $data = $request->validate([
            'certificate_type' => ['required', 'in:'.implode(',', array_keys(Certificate::TYPES))],
        ]);

        $this->createCertificate($student, $data['certificate_type']);

        return redirect()
            ->route('admin.students.progress.show', [
                'student' => $student,
                'tab' => 'certificate',
            ])
            ->with('status', 'student-progress-certificate-generated');
    }

    public function recreateCertificate(User $student, Certificate $certificate): RedirectResponse
    {
        $student = $this->resolveStudent($student);
        abort_unless($certificate->user_id === $student->id, 404);
        abort_unless($this->studentCanReceiveCertificates($student), 422);

        $this->createCertificate($student, $certificate->certificate_type);

        return redirect()
            ->route('admin.students.progress.show', [
                'student' => $student,
                'tab' => 'certificate',
            ])
            ->with('status', 'student-progress-certificate-recreated');
    }

    public function sendGraduationEmail(User $student): RedirectResponse
    {
        $student = $this->resolveStudent($student);
        $certificates = Certificate::query()
            ->where('user_id', $student->id)
            ->latest('generated_at')
            ->get();

        abort_unless($certificates->isNotEmpty(), 422);

        $this->sendEmail(
            $student,
            'Your YogaFX graduation certificate update',
            'Graduation Certificate Update',
            [
                'Your certificate records are ready in YogaFX LMS.',
                'Available certificates: '.$certificates
                    ->map(fn (Certificate $certificate) => $certificate->typeLabel().' v'.$certificate->version)
                    ->join(', '),
            ],
        );

        return redirect()
            ->route('admin.students.progress.show', [
                'student' => $student,
                'tab' => 'certificate',
            ])
            ->with('status', 'student-progress-graduation-email-sent');
    }

    public function downloadCertificate(User $student, Certificate $certificate)
    {
        $student = $this->resolveStudent($student);
        abort_unless($certificate->user_id === $student->id, 404);
        abort_unless(Storage::disk('local')->exists($certificate->file_path), 404);

        return Storage::disk('local')->download($certificate->file_path, $certificate->file_name);
    }

    public function destroyCertificate(User $student, Certificate $certificate): RedirectResponse
    {
        $student = $this->resolveStudent($student);
        abort_unless($certificate->user_id === $student->id, 404);

        if (Storage::disk('local')->exists($certificate->file_path)) {
            Storage::disk('local')->delete($certificate->file_path);
        }

        $certificate->delete();

        return redirect()
            ->route('admin.students.progress.show', [
                'student' => $student,
                'tab' => 'certificate',
            ])
            ->with('status', 'student-progress-certificate-deleted');
    }

    private function resolveStudent(User $student): User
    {
        abort_unless($student->isStudent(), 404);

        return $student->load('accessTier');
    }

    private function normalizeTab(string $tab): string
    {
        return in_array($tab, ['completed-lessons', 'assignment', 'certificate'], true)
            ? $tab
            : 'completed-lessons';
    }

    private function studentCanReceiveCertificates(User $student): bool
    {
        return $student->accessTier?->slug !== 'starter_kit'
            && $student->access_tier_id !== null;
    }

    private function createCertificate(User $student, string $certificateType): Certificate
    {
        $version = ((int) Certificate::withTrashed()
            ->where('user_id', $student->id)
            ->where('certificate_type', $certificateType)
            ->max('version')) + 1;

        $certificateLabel = Certificate::TYPES[$certificateType] ?? $certificateType;
        $timestamp = now();
        $safeStudentName = Str::slug($student->name ?: 'student');
        $safeType = Str::slug($certificateLabel);
        $fileName = "{$safeStudentName}-{$safeType}-v{$version}.html";
        $path = "certificates/{$student->id}/{$fileName}";

        $contents = view('certificates.template', [
            'student' => $student,
            'certificateLabel' => $certificateLabel,
            'generatedAt' => $timestamp,
            'version' => $version,
        ])->render();

        Storage::disk('local')->put($path, $contents);

        return Certificate::query()->create([
            'user_id' => $student->id,
            'certificate_type' => $certificateType,
            'file_path' => $path,
            'file_name' => $fileName,
            'version' => $version,
            'generated_by_user_id' => auth()->id(),
            'generated_at' => $timestamp,
        ]);
    }

    private function sendEmail(User $student, string $subject, string $heading, array $bodyLines): void
    {
        abort_if(blank($student->email), 422, 'Student email is required to send this message.');

        Mail::to($student->email)->send(
            new StudentProgressActionMail($subject, $heading, $bodyLines),
        );
    }
}
