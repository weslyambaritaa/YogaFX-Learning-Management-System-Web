<?php

namespace Tests\Feature;

use App\Models\AccessTier;
use App\Models\Course;
use App\Models\Ebook;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LearningContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_module_with_thumbnail_upload(): void
    {
        Storage::fake('local');

        $admin = User::factory()->admin()->create();
        $tier = AccessTier::factory()->create();

        $response = $this->actingAs($admin)->post(route('admin.modules.store'), [
            'title' => 'Breath Foundation',
            'url_slug' => 'breath-foundation',
            'thumbnail' => UploadedFile::fake()->image('module-thumbnail.jpg'),
            'access_tier_id' => $tier->id,
            'sort_order' => 1,
        ]);

        $response->assertSessionHasNoErrors();

        $module = Module::query()->where('url_slug', 'breath-foundation')->first();

        $this->assertNotNull($module);
        Storage::disk('local')->assertExists($module->thumbnail);
    }

    public function test_admin_cannot_delete_module_when_it_still_has_lessons(): void
    {
        $admin = User::factory()->admin()->create();
        $tier = AccessTier::factory()->create();
        $module = Module::factory()->create([
            'access_tier_id' => $tier->id,
        ]);

        Lesson::factory()->create([
            'module_id' => $module->id,
            'access_tier_id' => $tier->id,
        ]);

        $response = $this->actingAs($admin)->delete(route('admin.modules.destroy', $module));

        $response->assertSessionHasErrors('module');
        $this->assertDatabaseHas('modules', ['id' => $module->id]);
    }

    public function test_admin_can_create_lesson_with_workbook_upload(): void
    {
        Storage::fake('local');

        $admin = User::factory()->admin()->create();
        $tier = AccessTier::factory()->create();
        $module = Module::factory()->create([
            'access_tier_id' => $tier->id,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.lessons.store'), [
            'module_id' => $module->id,
            'access_tier_id' => $tier->id,
            'assessment_id' => null,
            'title' => 'Standing Warm Up',
            'thumbnail' => UploadedFile::fake()->image('lesson-thumbnail.jpg'),
            'workbook' => UploadedFile::fake()->create('workbook.pdf', 120, 'application/pdf'),
            'video' => 'https://example.com/video',
            'audio' => 'https://example.com/audio',
            'content' => '<p>Lesson content</p>',
            'sort_order' => 1,
        ]);

        $response->assertSessionHasNoErrors();

        $lesson = Lesson::query()->where('title', 'Standing Warm Up')->first();

        $this->assertNotNull($lesson);
        Storage::disk('local')->assertExists($lesson->thumbnail);
        Storage::disk('local')->assertExists($lesson->workbook);
    }

    public function test_student_only_sees_modules_for_their_access_tier(): void
    {
        $online = AccessTier::factory()->create(['name' => 'Online', 'slug' => 'online']);
        $starter = AccessTier::factory()->create(['name' => 'Starter Kit', 'slug' => 'starter_kit']);
        $student = User::factory()->student()->completeProfile()->create([
            'access_tier_id' => $online->id,
        ]);

        $visibleModule = Module::factory()->create([
            'title' => 'Visible Module',
            'access_tier_id' => $online->id,
        ]);
        Module::factory()->create([
            'title' => 'Hidden Module',
            'access_tier_id' => $starter->id,
        ]);

        $response = $this->actingAs($student)->get(route('modules.index'));

        $response->assertOk();
        $response->assertSee($visibleModule->title);
        $response->assertDontSee('Hidden Module');
    }

    public function test_student_cannot_open_lesson_from_another_access_tier(): void
    {
        $online = AccessTier::factory()->create(['name' => 'Online', 'slug' => 'online']);
        $starter = AccessTier::factory()->create(['name' => 'Starter Kit', 'slug' => 'starter_kit']);
        $student = User::factory()->student()->completeProfile()->create([
            'access_tier_id' => $online->id,
        ]);

        $starterModule = Module::factory()->create([
            'access_tier_id' => $starter->id,
        ]);
        $starterLesson = Lesson::factory()->create([
            'module_id' => $starterModule->id,
            'access_tier_id' => $starter->id,
        ]);

        $this->actingAs($student)
            ->get(route('lessons.show', $starterLesson))
            ->assertForbidden();
    }

    public function test_student_only_sees_ebooks_and_courses_for_their_access_tier(): void
    {
        $online = AccessTier::factory()->create(['name' => 'Online', 'slug' => 'online']);
        $starter = AccessTier::factory()->create(['name' => 'Starter Kit', 'slug' => 'starter_kit']);
        $student = User::factory()->student()->completeProfile()->create([
            'access_tier_id' => $online->id,
        ]);

        $visibleEbook = Ebook::factory()->create([
            'title' => 'Visible Ebook',
            'access_tier_id' => $online->id,
        ]);
        Ebook::factory()->create([
            'title' => 'Hidden Ebook',
            'access_tier_id' => $starter->id,
        ]);

        $visibleCourse = Course::factory()->create([
            'title' => 'Visible Course',
            'access_tier_id' => $online->id,
        ]);
        Course::factory()->create([
            'title' => 'Hidden Course',
            'access_tier_id' => $starter->id,
        ]);

        $this->actingAs($student)
            ->get(route('ebooks.index'))
            ->assertOk()
            ->assertSee($visibleEbook->title)
            ->assertDontSee('Hidden Ebook');

        $this->actingAs($student)
            ->get(route('courses.index'))
            ->assertOk()
            ->assertSee($visibleCourse->title)
            ->assertDontSee('Hidden Course');
    }
}
