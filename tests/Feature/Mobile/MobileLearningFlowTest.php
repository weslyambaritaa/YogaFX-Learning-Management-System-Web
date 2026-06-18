<?php

namespace Tests\Feature\Mobile;

use App\Models\AccessTier;
use App\Models\Assignment;
use App\Models\Certificate;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileLearningFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_modules_index_returns_visible_and_locked_module_states(): void
    {
        [$student, $tier] = $this->createActiveStudentWithTier();

        $firstModule = Module::factory()->create([
            'title' => 'Foundation Flow',
            'sort_order' => 1,
        ]);
        $firstModule->accessTiers()->sync([$tier->id]);

        $firstLesson = Lesson::factory()->create([
            'module_id' => $firstModule->id,
            'title' => 'Foundation Lesson',
            'sort_order' => 1,
        ]);
        $firstLesson->accessTiers()->sync([$tier->id]);

        $secondModule = Module::factory()->create([
            'title' => 'Advanced Flow',
            'sort_order' => 2,
        ]);
        $secondModule->accessTiers()->sync([$tier->id]);

        $secondLesson = Lesson::factory()->create([
            'module_id' => $secondModule->id,
            'title' => 'Advanced Lesson',
            'sort_order' => 1,
        ]);
        $secondLesson->accessTiers()->sync([$tier->id]);

        Sanctum::actingAs($student);

        $this->getJson('/api/mobile/v1/modules')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.items.0.id', $firstModule->id)
            ->assertJsonPath('data.items.0.is_visible', true)
            ->assertJsonPath('data.items.0.status', 'available')
            ->assertJsonPath('data.items.1.id', $secondModule->id)
            ->assertJsonPath('data.items.1.is_visible', false)
            ->assertJsonPath('data.items.1.status', 'locked');
    }

    public function test_mobile_module_detail_returns_lock_reason_for_locked_module(): void
    {
        [$student, $tier] = $this->createActiveStudentWithTier();

        $firstModule = Module::factory()->create(['sort_order' => 1]);
        $firstModule->accessTiers()->sync([$tier->id]);

        $firstLesson = Lesson::factory()->create([
            'module_id' => $firstModule->id,
            'sort_order' => 1,
        ]);
        $firstLesson->accessTiers()->sync([$tier->id]);

        $lockedModule = Module::factory()->create(['sort_order' => 2]);
        $lockedModule->accessTiers()->sync([$tier->id]);

        $lockedLesson = Lesson::factory()->create([
            'module_id' => $lockedModule->id,
            'sort_order' => 1,
        ]);
        $lockedLesson->accessTiers()->sync([$tier->id]);

        Sanctum::actingAs($student);

        $this->getJson("/api/mobile/v1/modules/{$lockedModule->id}")
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'This module is still locked for the authenticated student.')
            ->assertJsonPath('errors.lock_reason', 'Complete the previous module requirements before opening this module.');
    }

    public function test_mobile_lesson_detail_returns_lock_reason_for_locked_lesson(): void
    {
        [$student, $tier] = $this->createActiveStudentWithTier();

        $module = Module::factory()->create();
        $module->accessTiers()->sync([$tier->id]);

        $firstLesson = Lesson::factory()->create([
            'module_id' => $module->id,
            'title' => 'Lesson One',
            'sort_order' => 1,
        ]);
        $firstLesson->accessTiers()->sync([$tier->id]);

        $secondLesson = Lesson::factory()->create([
            'module_id' => $module->id,
            'title' => 'Lesson Two',
            'sort_order' => 2,
        ]);
        $secondLesson->accessTiers()->sync([$tier->id]);

        Sanctum::actingAs($student);

        $this->getJson("/api/mobile/v1/lessons/{$secondLesson->id}")
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'This lesson is still locked for the authenticated student.')
            ->assertJsonPath('errors.lock_reason', 'Complete the lesson video to at least 95% before continuing.');
    }

    public function test_mobile_lesson_progress_update_does_not_reduce_existing_progress(): void
    {
        [$student, $tier] = $this->createActiveStudentWithTier();

        $module = Module::factory()->create();
        $module->accessTiers()->sync([$tier->id]);

        $lesson = Lesson::factory()->create([
            'module_id' => $module->id,
            'sort_order' => 1,
        ]);
        $lesson->accessTiers()->sync([$tier->id]);

        LessonProgress::factory()->create([
            'user_id' => $student->id,
            'lesson_id' => $lesson->id,
            'watch_progress' => 60,
            'is_done' => false,
            'completed_at' => null,
            'video_completed_at' => null,
        ]);

        Sanctum::actingAs($student);

        $this->postJson("/api/mobile/v1/lessons/{$lesson->id}/progress", [
            'watch_progress' => 30,
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.watch_progress', 60)
            ->assertJsonPath('data.is_done', false)
            ->assertJsonPath('data.assessment_unlocked', false);

        $this->assertDatabaseHas('lesson_progress', [
            'user_id' => $student->id,
            'lesson_id' => $lesson->id,
            'watch_progress' => 60.00,
        ]);
    }

    public function test_mobile_assignment_detail_respects_locked_module_gate(): void
    {
        [$student, $tier] = $this->createActiveStudentWithTier();

        $firstModule = Module::factory()->create(['sort_order' => 1]);
        $firstModule->accessTiers()->sync([$tier->id]);

        $firstLesson = Lesson::factory()->create([
            'module_id' => $firstModule->id,
            'sort_order' => 1,
        ]);
        $firstLesson->accessTiers()->sync([$tier->id]);

        $secondModule = Module::factory()->create(['sort_order' => 2]);
        $secondModule->accessTiers()->sync([$tier->id]);

        $assignment = Assignment::factory()->create([
            'module_id' => $secondModule->id,
            'status' => Assignment::STATUS_LIVE,
        ]);

        Sanctum::actingAs($student);

        $this->getJson("/api/mobile/v1/assignments/{$assignment->id}")
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'This assignment is still locked for the authenticated student.')
            ->assertJsonPath('errors.lock_reason', 'Complete the previous module requirements before opening this module.');
    }

    public function test_student_cannot_access_another_students_mobile_certificate_detail(): void
    {
        [$student] = $this->createActiveStudentWithTier();
        [$otherStudent] = $this->createActiveStudentWithTier('master_class');

        $certificate = Certificate::factory()->create([
            'user_id' => $otherStudent->id,
        ]);

        Sanctum::actingAs($student);

        $this->getJson("/api/mobile/v1/certificates/{$certificate->id}")
            ->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Certificate not found for the authenticated student.');
    }

    /**
     * @return array{0: User, 1: AccessTier}
     */
    private function createActiveStudentWithTier(string $slug = 'online'): array
    {
        $tier = AccessTier::factory()->create([
            'name' => str($slug)->replace('_', ' ')->title()->value(),
            'slug' => $slug,
        ]);

        $student = User::factory()
            ->student()
            ->completeProfile()
            ->create([
                'access_tier_id' => $tier->id,
                'is_active' => true,
            ]);

        return [$student, $tier];
    }
}
