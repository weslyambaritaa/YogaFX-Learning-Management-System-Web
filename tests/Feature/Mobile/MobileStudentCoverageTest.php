<?php

namespace Tests\Feature\Mobile;

use App\Models\AccessTier;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\DialogContent;
use App\Models\Ebook;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileStudentCoverageTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_dashboard_includes_dialogs_and_ebook_resources(): void
    {
        [$student, $tier] = $this->createActiveStudentWithTier();

        DialogContent::query()->create([
            'key' => DialogContent::KEY_FULL_STANDING,
            'title' => 'Standing Flow',
            'content' => '<p>Ready</p>',
        ]);

        Ebook::factory()->create([
            'title' => 'Daily Practice Guide',
            'sort_order' => 1,
        ])->accessTiers()->sync([$tier->id]);

        Sanctum::actingAs($student);

        $this->getJson('/api/mobile/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.dialogs.0.key', DialogContent::KEY_FULL_STANDING)
            ->assertJsonPath('data.dialogs.0.route_key', 'full-standing')
            ->assertJsonPath('data.dialogs.0.title', 'Standing Flow')
            ->assertJsonPath('data.dialogs.0.has_content', true)
            ->assertJsonPath('data.ebook_resources.total', 1)
            ->assertJsonPath('data.ebook_resources.items.0.title', 'Daily Practice Guide')
            ->assertJsonPath('data.home_stage', 12)
            ->assertJsonPath('data.student_context.access_tier.slug', 'online')
            ->assertJsonStructure([
                'data' => [
                    'access_time_summary',
                    'continue_learning_section',
                    'progress_summary_section',
                    'next_step',
                    'sequential_awareness',
                    'available_modules_section',
                    'assignment_milestone',
                    'certificate_milestone',
                    'ebook_resources_section',
                    'home_experience',
                ],
            ]);
    }

    public function test_mobile_module_detail_includes_related_student_resources(): void
    {
        [$student, $tier] = $this->createActiveStudentWithTier('master_class');

        $module = Module::factory()->create([
            'sort_order' => 1,
            'ebook_enabled' => true,
            'video_lecturer_enabled' => true,
            'certificate_enabled' => true,
        ]);
        $module->accessTiers()->sync([$tier->id]);

        $lesson = Lesson::factory()->create([
            'module_id' => $module->id,
            'title' => 'Core Practice',
            'sort_order' => 1,
        ]);
        $lesson->accessTiers()->sync([$tier->id]);

        LessonProgress::factory()->create([
            'user_id' => $student->id,
            'lesson_id' => $lesson->id,
            'watch_progress' => 100,
            'is_done' => true,
            'completed_at' => now(),
            'video_completed_at' => now(),
        ]);

        Ebook::factory()->create([
            'title' => 'Support Ebook',
            'sort_order' => 1,
        ])->accessTiers()->sync([$tier->id]);

        Course::factory()->create([
            'title' => 'Lecturer Session',
            'access_tier_id' => $tier->id,
        ])->accessTiers()->sync([$tier->id]);

        Certificate::factory()->create([
            'user_id' => $student->id,
        ]);

        Sanctum::actingAs($student);

        $this->getJson("/api/mobile/v1/modules/{$module->id}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.view_type', 'learning')
            ->assertJsonPath('data.ebooks.0.title', 'Support Ebook')
            ->assertJsonPath('data.video_lecturers.0.title', 'Lecturer Session')
            ->assertJsonStructure([
                'data' => [
                    'certificates',
                    'certificate_summary' => ['generated_items'],
                ],
            ]);
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
