<?php

namespace Tests\Feature;

use App\Mail\StudentProgressActionMail;
use App\Models\AccessTier;
use App\Models\AssignmentSubmission;
use App\Models\Certificate;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StudentProgressTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_student_progress_page(): void
    {
        Storage::fake('local');

        [$admin, $student, $lesson] = $this->createStudentProgressContext();

        LessonProgress::factory()->create([
            'user_id' => $student->id,
            'lesson_id' => $lesson->id,
        ]);

        AssignmentSubmission::factory()->create([
            'user_id' => $student->id,
            'assignment_type' => 'graduation_video',
            'assignment_feedback' => 'Keep your shoulders relaxed.',
            'assignment_status' => AssignmentSubmission::STATUS_REJECTED,
        ]);

        Storage::disk('local')->put('certificates/sample.html', '<h1>Certificate</h1>');
        Certificate::factory()->create([
            'user_id' => $student->id,
            'generated_by_user_id' => $admin->id,
            'certificate_type' => Certificate::TYPE_BIKRAM,
            'file_path' => 'certificates/sample.html',
            'file_name' => 'sample.html',
        ]);

        $response = $this->actingAs($admin)->get(
            route('admin.students.progress.show', $student),
        );

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Students/Progress')
            ->where('student.id', $student->id)
            ->where('activeTab', 'completed-lessons')
            ->has('completedLessons', 1)
            ->where('completedLessons.0.lesson_title', $lesson->title)
            ->has('assignments', 1)
            ->where('assignments.0.title', 'Graduation Video')
            ->has('certificates', 1)
            ->where('certificates.0.type_label', 'Bikram Yoga Certificate'));
    }

    public function test_admin_can_reset_completed_lesson_progress(): void
    {
        [$admin, $student, $lesson] = $this->createStudentProgressContext();

        $progress = LessonProgress::factory()->create([
            'user_id' => $student->id,
            'lesson_id' => $lesson->id,
            'watch_progress' => 100,
            'is_done' => true,
            'completed_at' => now(),
        ]);

        $response = $this->actingAs($admin)->post(
            route('admin.students.progress.lessons.reset', [
                'student' => $student,
                'lessonProgress' => $progress,
            ]),
        );

        $response->assertRedirect(
            route('admin.students.progress.show', [
                'student' => $student,
                'tab' => 'completed-lessons',
            ]),
        );

        $this->assertDatabaseHas('lesson_progress', [
            'id' => $progress->id,
            'is_done' => false,
            'is_workbook_downloaded' => false,
        ]);
    }

    public function test_admin_can_save_assignment_changes(): void
    {
        [$admin, $student] = $this->createStudentProgressContext();

        $assignment = AssignmentSubmission::factory()->create([
            'user_id' => $student->id,
            'assignment_status' => AssignmentSubmission::STATUS_PENDING_REVIEW,
            'assignment_feedback' => null,
        ]);

        $response = $this->actingAs($admin)->patch(
            route('admin.students.progress.assignments.update', [
                'student' => $student,
                'assignmentSubmission' => $assignment,
            ]),
            [
                'assignment_status' => AssignmentSubmission::STATUS_REJECTED,
                'assignment_feedback' => 'Please upload a clearer graduation video.',
            ],
        );

        $response->assertRedirect(
            route('admin.students.progress.show', [
                'student' => $student,
                'tab' => 'assignment',
            ]),
        );

        $this->assertDatabaseHas('assignment_submissions', [
            'id' => $assignment->id,
            'assignment_status' => AssignmentSubmission::STATUS_REJECTED,
            'assignment_feedback' => 'Please upload a clearer graduation video.',
        ]);
    }

    public function test_admin_can_send_assignment_email_and_delete_video(): void
    {
        Mail::fake();

        [$admin, $student] = $this->createStudentProgressContext();

        $assignment = AssignmentSubmission::factory()->create([
            'user_id' => $student->id,
            'assignment_type' => 'graduation_video',
            'assignment_video' => 'https://example.com/graduation-video',
            'assignment_status' => AssignmentSubmission::STATUS_APPROVED,
        ]);

        $this->actingAs($admin)->post(
            route('admin.students.progress.assignments.send-email', [
                'student' => $student,
                'assignmentSubmission' => $assignment,
            ]),
        )->assertRedirect();

        Mail::assertSent(StudentProgressActionMail::class, function (StudentProgressActionMail $mail) use ($student) {
            return $mail->hasTo($student->email)
                && $mail->subjectLine === 'Your YogaFX assignment update';
        });

        $this->actingAs($admin)->delete(
            route('admin.students.progress.assignments.delete-video', [
                'student' => $student,
                'assignmentSubmission' => $assignment,
            ]),
        )->assertRedirect();

        $this->assertDatabaseHas('assignment_submissions', [
            'id' => $assignment->id,
            'assignment_video' => null,
        ]);
    }

    public function test_admin_can_generate_recreate_download_delete_and_email_certificate(): void
    {
        Storage::fake('local');
        Mail::fake();

        [$admin, $student] = $this->createStudentProgressContext();

        $generateResponse = $this->actingAs($admin)->post(
            route('admin.students.progress.certificates.store', ['student' => $student]),
            ['certificate_type' => Certificate::TYPE_BIKRAM],
        );

        $generateResponse->assertRedirect();

        $certificate = Certificate::query()->where('user_id', $student->id)->first();

        $this->assertNotNull($certificate);
        Storage::disk('local')->assertExists($certificate->file_path);

        $this->actingAs($admin)->get(
            route('admin.students.progress.certificates.download', [
                'student' => $student,
                'certificate' => $certificate,
            ]),
        )->assertOk();

        $this->actingAs($admin)->post(
            route('admin.students.progress.certificates.recreate', [
                'student' => $student,
                'certificate' => $certificate,
            ]),
        )->assertRedirect();

        $this->assertDatabaseHas('certificates', [
            'user_id' => $student->id,
            'certificate_type' => Certificate::TYPE_BIKRAM,
            'version' => 2,
        ]);

        $this->actingAs($admin)->post(
            route('admin.students.progress.certificates.send-graduation-email', [
                'student' => $student,
            ]),
        )->assertRedirect();

        Mail::assertSent(StudentProgressActionMail::class, function (StudentProgressActionMail $mail) use ($student) {
            return $mail->hasTo($student->email)
                && $mail->subjectLine === 'Your YogaFX graduation certificate update';
        });

        $this->actingAs($admin)->delete(
            route('admin.students.progress.certificates.destroy', [
                'student' => $student,
                'certificate' => $certificate,
            ]),
        )->assertRedirect();

        $this->assertSoftDeleted('certificates', [
            'id' => $certificate->id,
        ]);
    }

    /**
     * @return array{0: User, 1: User, 2?: Lesson}
     */
    private function createStudentProgressContext(): array
    {
        $admin = User::factory()->admin()->create();
        $tier = AccessTier::factory()->create([
            'name' => 'Online',
            'slug' => 'online',
        ]);
        $student = User::factory()->student()->completeProfile()->create([
            'access_tier_id' => $tier->id,
        ]);

        $module = Module::factory()->create([
            'title' => 'Foundational Module',
        ]);
        $module->accessTiers()->sync([$tier->id]);

        $lesson = Lesson::factory()->create([
            'module_id' => $module->id,
            'title' => 'Completed Breathing Practice',
        ]);
        $lesson->accessTiers()->sync([$tier->id]);

        return [$admin, $student, $lesson];
    }
}
