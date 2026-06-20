<?php

namespace Tests\Feature;

use App\Mail\StudentProgressActionMail;
use App\Models\AccessTier;
use App\Models\CertificateDownloadEvent;
use App\Models\AssignmentSubmission;
use App\Models\Certificate;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Module;
use App\Models\StudentModuleVisit;
use App\Models\User;
use App\Services\Certificates\CertificateEligibilityService;
use App\Services\Certificates\CertificateGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use Tests\TestCase;

class StudentProgressTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_student_progress_directory_grouped_by_tier(): void
    {
        $admin = User::factory()->admin()->create();
        $masterclassTier = AccessTier::factory()->create([
            'name' => 'Masterclass',
            'slug' => AccessTier::SLUG_MASTER_CLASS,
        ]);
        $onlineTier = AccessTier::factory()->create([
            'name' => 'Online',
            'slug' => AccessTier::SLUG_ONLINE,
        ]);
        $starterKitTier = AccessTier::factory()->create([
            'name' => 'Starter Kit',
            'slug' => AccessTier::SLUG_STARTER_KIT,
        ]);

        $masterclassStudent = User::factory()->student()->completeProfile()->create([
            'access_tier_id' => $masterclassTier->id,
            'profile_photo' => 'https://example.com/masterclass.jpg',
        ]);
        $onlineStudent = User::factory()->student()->completeProfile()->create([
            'access_tier_id' => $onlineTier->id,
        ]);
        $starterKitStudent = User::factory()->student()->completeProfile()->create([
            'access_tier_id' => $starterKitTier->id,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.student-progress.index'));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Admin/StudentProgress/Directory')
            ->has('tierSections', 3)
            ->where('tierSections.0.label', 'Masterclass')
            ->where('tierSections.0.students.0.name', $masterclassStudent->name)
            ->where('tierSections.0.students.0.assignment_status', 'Not Available')
            ->where('tierSections.1.label', 'Online')
            ->where('tierSections.1.students.0.name', $onlineStudent->name)
            ->where('tierSections.1.students.0.assignment_status', 'Not Available')
            ->where('tierSections.2.label', 'Starter Kit')
            ->where('tierSections.2.students.0.name', $starterKitStudent->name)
            ->where('tierSections.2.students.0.assignment_status', 'Not Available'));
    }

    public function test_admin_can_view_completed_lesson_student_progress_page(): void
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
            route('admin.student-progress.completed-lessons.show', $student),
        );

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Admin/StudentProgress/CompletedLessons')
            ->where('student.id', $student->id)
            ->has('completedLessons', 1)
            ->where('completedLessons.0.lesson_title', $lesson->title));
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
            route('admin.student-progress.completed-lessons.reset', [
                'student' => $student,
                'lessonProgress' => $progress,
            ]),
        );

        $response->assertRedirect(
            route('admin.student-progress.completed-lessons.show', $student),
        );

        $this->assertDatabaseHas('lesson_progress', [
            'id' => $progress->id,
            'is_done' => false,
            'is_workbook_downloaded' => false,
        ]);
    }

    public function test_admin_reset_student_progress_clears_non_lesson_module_completion_sources(): void
    {
        [$admin, $student, $lesson] = $this->createStudentProgressContext();

        LessonProgress::factory()->create([
            'user_id' => $student->id,
            'lesson_id' => $lesson->id,
        ]);

        $assignmentModule = Module::factory()->create([
            'title' => 'Assignment Reset Module',
            'url_slug' => 'assignment-reset-module',
        ]);
        $resourceModule = Module::factory()->create([
            'title' => 'Ebook Reset Module',
            'url_slug' => 'ebook-reset-module',
            'ebook_enabled' => true,
        ]);
        $certificateModule = Module::factory()->create([
            'title' => 'Certificate Reset Module',
            'url_slug' => 'certificate-reset-module',
            'certificate_enabled' => true,
        ]);

        AssignmentSubmission::factory()->create([
            'user_id' => $student->id,
            'assignment_id' => null,
            'assignment_type' => 'graduation_video',
            'assignment_video' => 'https://example.com/reset-video.mp4',
            'assignment_status' => AssignmentSubmission::STATUS_APPROVED,
        ]);

        StudentModuleVisit::query()->create([
            'user_id' => $student->id,
            'module_id' => $resourceModule->id,
            'opened_at' => now(),
        ]);

        CertificateDownloadEvent::query()->create([
            'user_id' => $student->id,
            'module_id' => $certificateModule->id,
            'certificate_id' => null,
            'downloaded_at' => now(),
        ]);

        $response = $this->actingAs($admin)->post(
            route('admin.students.reset-progress', $student),
        );

        $response->assertRedirect(route('admin.students.edit', $student));

        $this->assertDatabaseMissing('lesson_progress', [
            'user_id' => $student->id,
            'lesson_id' => $lesson->id,
        ]);
        $this->assertDatabaseMissing('assignment_submissions', [
            'user_id' => $student->id,
            'assignment_type' => 'graduation_video',
        ]);
        $this->assertDatabaseMissing('student_module_visits', [
            'user_id' => $student->id,
            'module_id' => $resourceModule->id,
        ]);
        $this->assertDatabaseMissing('certificate_download_events', [
            'user_id' => $student->id,
            'module_id' => $certificateModule->id,
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
            route('admin.student-progress.assignments.update', [
                'student' => $student,
                'assignmentSubmission' => $assignment,
            ]),
            [
                'assignment_status' => AssignmentSubmission::STATUS_REJECTED,
                'assignment_feedback' => 'Please upload a clearer graduation video.',
            ],
        );

        $response->assertRedirect(
            route('admin.student-progress.assignments.show', $student),
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
            route('admin.student-progress.assignments.send-email', [
                'student' => $student,
                'assignmentSubmission' => $assignment,
            ]),
        )->assertRedirect();

        Mail::assertSent(StudentProgressActionMail::class, function (StudentProgressActionMail $mail) use ($student) {
            return $mail->hasTo($student->email)
                && $mail->subjectLine === 'Your YogaFX assignment update';
        });

        $this->actingAs($admin)->delete(
            route('admin.student-progress.assignments.delete-video', [
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

        [$admin, $student, $lesson] = $this->createStudentProgressContext();

        LessonProgress::factory()->create([
            'user_id' => $student->id,
            'lesson_id' => $lesson->id,
            'watch_progress' => 100,
            'is_done' => true,
            'completed_at' => now(),
        ]);

        $generateResponse = $this->actingAs($admin)->post(
            route('admin.student-progress.certificates.store', ['student' => $student]),
            ['certificate_type' => Certificate::TYPE_BIKRAM],
        );

        $generateResponse->assertRedirect();

        $certificate = Certificate::query()->where('user_id', $student->id)->first();

        $this->assertNotNull($certificate);
        Storage::disk('local')->assertExists($certificate->file_path);

        $this->actingAs($admin)->get(
            route('admin.student-progress.certificates.download', [
                'student' => $student,
                'certificate' => $certificate,
            ]),
        )->assertOk();

        $this->actingAs($admin)->post(
            route('admin.student-progress.certificates.recreate', [
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
            route('admin.student-progress.certificates.send-graduation-email', [
                'student' => $student,
            ]),
        )->assertRedirect();

        Mail::assertSent(StudentProgressActionMail::class, function (StudentProgressActionMail $mail) use ($student) {
            return $mail->hasTo($student->email)
                && $mail->subjectLine === 'Your YogaFX graduation certificate update';
        });

        $this->actingAs($admin)->delete(
            route('admin.student-progress.certificates.destroy', [
                'student' => $student,
                'certificate' => $certificate,
            ]),
        )->assertRedirect();

        $this->assertSoftDeleted('certificates', [
            'id' => $certificate->id,
        ]);
    }

    public function test_admin_can_generate_certificate_when_bonus_module_without_lessons_exists(): void
    {
        [$admin, $student, $lesson] = $this->createStudentProgressContext();

        LessonProgress::factory()->create([
            'user_id' => $student->id,
            'lesson_id' => $lesson->id,
            'watch_progress' => 100,
            'is_done' => true,
            'completed_at' => now(),
        ]);

        $bonusModule = Module::factory()->create([
            'title' => 'Bonus Module',
            'url_slug' => 'bonus-module',
            'ebook_enabled' => true,
        ]);
        $bonusModule->accessTiers()->sync([$student->access_tier_id]);

        $summary = app(CertificateEligibilityService::class)->summaryForStudent($student->fresh());
        $learningPathRequirement = collect($summary['requirements'])->firstWhere('key', 'learning_path');

        $this->assertTrue($summary['learning_eligible']);
        $this->assertSame(1, $learningPathRequirement['total']);
        $this->assertSame(1, $learningPathRequirement['completed']);

        $this->app->instance(CertificateGeneratorService::class, Mockery::mock(CertificateGeneratorService::class, function ($mock) use ($student, $admin) {
            $mock->shouldReceive('generate')
                ->once()
                ->andReturnUsing(fn () => Certificate::factory()->create([
                    'user_id' => $student->id,
                    'generated_by_user_id' => $admin->id,
                    'certificate_type' => Certificate::TYPE_BIKRAM,
                    'file_path' => 'certificates/test-bonus.pdf',
                    'file_name' => 'test-bonus.pdf',
                ]));
        }));

        $response = $this->actingAs($admin)->post(
            route('admin.student-progress.certificates.store', ['student' => $student]),
            ['certificate_type' => Certificate::TYPE_BIKRAM],
        );

        $response->assertRedirect(route('admin.student-progress.certificates.show', $student));

        $this->assertDatabaseHas('certificates', [
            'user_id' => $student->id,
            'certificate_type' => Certificate::TYPE_BIKRAM,
            'file_name' => 'test-bonus.pdf',
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
