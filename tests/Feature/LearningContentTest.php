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
use Inertia\Testing\AssertableInertia as Assert;
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
            'access_tier_ids' => [$tier->id],
        ]);

        $response->assertSessionHasNoErrors();

        $module = Module::query()->where('url_slug', 'breath-foundation')->first();

        $this->assertNotNull($module);
        $this->assertSame(1, $module->sort_order);
        Storage::disk('local')->assertExists($module->thumbnail);
        $this->assertDatabaseHas('access_tier_module', [
            'module_id' => $module->id,
            'access_tier_id' => $tier->id,
        ]);
    }

    public function test_admin_cannot_delete_module_when_it_still_has_lessons(): void
    {
        $admin = User::factory()->admin()->create();
        $tier = AccessTier::factory()->create();
        $module = Module::factory()->create();
        $module->accessTiers()->sync([$tier->id]);

        $lesson = Lesson::factory()->create([
            'module_id' => $module->id,
        ]);
        $lesson->accessTiers()->sync([$tier->id]);

        $response = $this->actingAs($admin)->delete(route('admin.modules.destroy', $module));

        $response->assertSessionHasErrors('module');
        $this->assertDatabaseHas('modules', ['id' => $module->id]);
    }

    public function test_admin_can_create_lesson_with_workbook_upload(): void
    {
        Storage::fake('local');

        $admin = User::factory()->admin()->create();
        $tier = AccessTier::factory()->create();
        $module = Module::factory()->create();
        $module->accessTiers()->sync([$tier->id]);

        $response = $this->actingAs($admin)->post(route('admin.lessons.store'), [
            'module_id' => $module->id,
            'access_tier_ids' => [$tier->id],
            'assessment_id' => null,
            'title' => 'Standing Warm Up',
            'thumbnail' => UploadedFile::fake()->image('lesson-thumbnail.jpg'),
            'workbook' => UploadedFile::fake()->create('workbook.pdf', 120, 'application/pdf'),
            'video' => 'https://example.com/video',
            'audio' => 'https://example.com/audio',
            'content' => '<p>Lesson content</p>',
        ]);

        $response->assertSessionHasNoErrors();

        $lesson = Lesson::query()->where('title', 'Standing Warm Up')->first();

        $this->assertNotNull($lesson);
        $this->assertSame(1, $lesson->sort_order);
        Storage::disk('local')->assertExists($lesson->thumbnail);
        Storage::disk('local')->assertExists($lesson->workbook);
        $this->assertDatabaseHas('access_tier_lesson', [
            'lesson_id' => $lesson->id,
            'access_tier_id' => $tier->id,
        ]);
    }

    public function test_admin_cannot_create_lesson_with_tier_outside_module_tiers(): void
    {
        Storage::fake('local');

        $admin = User::factory()->admin()->create();
        $online = AccessTier::factory()->create(['name' => 'Online']);
        $masterClass = AccessTier::factory()->create(['name' => 'MasterClass']);
        $starterKit = AccessTier::factory()->create(['name' => 'Starter Kit']);

        $module = Module::factory()->create([
            'title' => 'Advanced Flow',
        ]);
        $module->accessTiers()->sync([$online->id, $masterClass->id]);

        $response = $this->actingAs($admin)->post(route('admin.lessons.store'), [
            'module_id' => $module->id,
            'access_tier_ids' => [$starterKit->id],
            'assessment_id' => null,
            'title' => 'Invalid Tier Lesson',
            'thumbnail' => UploadedFile::fake()->image('invalid-tier-lesson.jpg'),
            'workbook' => null,
            'video' => 'https://example.com/video',
            'audio' => null,
            'content' => '<p>Invalid tier content</p>',
        ]);

        $response->assertSessionHasErrors('access_tier_ids');
        $this->assertDatabaseMissing('lessons', [
            'title' => 'Invalid Tier Lesson',
        ]);
    }

    public function test_admin_cannot_update_lesson_with_tier_outside_module_tiers(): void
    {
        Storage::fake('local');

        $admin = User::factory()->admin()->create();
        $online = AccessTier::factory()->create(['name' => 'Online']);
        $masterClass = AccessTier::factory()->create(['name' => 'MasterClass']);
        $starterKit = AccessTier::factory()->create(['name' => 'Starter Kit']);

        $module = Module::factory()->create([
            'title' => 'Intermediate Flow',
        ]);
        $module->accessTiers()->sync([$online->id, $masterClass->id]);

        $lesson = Lesson::factory()->create([
            'module_id' => $module->id,
            'title' => 'Valid Lesson',
        ]);
        $lesson->accessTiers()->sync([$online->id]);

        $response = $this->actingAs($admin)->patch(route('admin.lessons.update', $lesson), [
            'module_id' => $module->id,
            'access_tier_ids' => [$online->id, $starterKit->id],
            'assessment_id' => null,
            'title' => 'Valid Lesson',
            'video' => 'https://example.com/video',
            'audio' => null,
            'content' => '<p>Updated content</p>',
        ]);

        $response->assertSessionHasErrors('access_tier_ids');

        $this->assertDatabaseHas('access_tier_lesson', [
            'lesson_id' => $lesson->id,
            'access_tier_id' => $online->id,
        ]);
        $this->assertDatabaseMissing('access_tier_lesson', [
            'lesson_id' => $lesson->id,
            'access_tier_id' => $starterKit->id,
        ]);
    }

    public function test_admin_can_create_ebook_with_auto_sort_order_and_multiple_tiers(): void
    {
        Storage::fake('local');

        $admin = User::factory()->admin()->create();
        $starter = AccessTier::factory()->create(['name' => 'Starter Kit']);
        $online = AccessTier::factory()->create(['name' => 'Online']);

        $response = $this->actingAs($admin)->post(route('admin.ebooks.store'), [
            'title' => 'Foundations Guide',
            'file' => UploadedFile::fake()->create('foundations-guide.pdf', 120, 'application/pdf'),
            'access_tier_ids' => [$starter->id, $online->id],
        ]);

        $response->assertSessionHasNoErrors();

        $ebook = Ebook::query()->where('title', 'Foundations Guide')->first();

        $this->assertNotNull($ebook);
        $this->assertSame(1, $ebook->sort_order);
        Storage::disk('local')->assertExists($ebook->file);
        $this->assertDatabaseHas('access_tier_ebook', [
            'ebook_id' => $ebook->id,
            'access_tier_id' => $starter->id,
        ]);
        $this->assertDatabaseHas('access_tier_ebook', [
            'ebook_id' => $ebook->id,
            'access_tier_id' => $online->id,
        ]);
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
        ]);
        $visibleModule->accessTiers()->sync([$online->id]);

        $hiddenModule = Module::factory()->create([
            'title' => 'Hidden Module',
        ]);
        $hiddenModule->accessTiers()->sync([$starter->id]);

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

        $starterModule = Module::factory()->create();
        $starterModule->accessTiers()->sync([$starter->id]);
        $starterLesson = Lesson::factory()->create([
            'module_id' => $starterModule->id,
        ]);
        $starterLesson->accessTiers()->sync([$starter->id]);

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
        ]);
        $visibleEbook->accessTiers()->sync([$online->id]);

        $hiddenEbook = Ebook::factory()->create([
            'title' => 'Hidden Ebook',
        ]);
        $hiddenEbook->accessTiers()->sync([$starter->id]);

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

    public function test_student_can_open_pdf_ebook_preview_page_with_separate_download_url(): void
    {
        Storage::fake('local');

        $online = AccessTier::factory()->create(['name' => 'Online', 'slug' => 'online']);
        $student = User::factory()->student()->completeProfile()->create([
            'access_tier_id' => $online->id,
        ]);

        $path = 'ebooks/files/previewable.pdf';
        Storage::disk('local')->put($path, 'sample pdf content');

        $ebook = Ebook::factory()->create([
            'title' => 'Previewable Ebook',
            'file' => $path,
        ]);
        $ebook->accessTiers()->sync([$online->id]);

        $this->actingAs($student)
            ->get(route('ebooks.preview', $ebook))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Ebooks/Preview')
                ->where('ebook.title', 'Previewable Ebook')
                ->where('ebook.preview_supported', true)
                ->where('backLabel', 'Back to Ebooks')
                ->where('ebook.download_url', route('media.show', [
                    'entity' => 'ebook',
                    'id' => $ebook->id,
                    'field' => 'file',
                    'download' => 1,
                ])));
    }

    public function test_student_sees_fallback_message_for_non_previewable_ebook_file(): void
    {
        Storage::fake('local');

        $online = AccessTier::factory()->create(['name' => 'Online', 'slug' => 'online']);
        $student = User::factory()->student()->completeProfile()->create([
            'access_tier_id' => $online->id,
        ]);

        $path = 'ebooks/files/reference.txt';
        Storage::disk('local')->put($path, 'plain text reference');

        $ebook = Ebook::factory()->create([
            'title' => 'Reference Notes',
            'file' => $path,
        ]);
        $ebook->accessTiers()->sync([$online->id]);

        $this->actingAs($student)
            ->get(route('ebooks.preview', $ebook))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Ebooks/Preview')
                ->where('ebook.title', 'Reference Notes')
                ->where('ebook.preview_supported', false)
                ->where('ebook.preview_message', 'This ebook file cannot be previewed in the browser yet. You can still download it.'));
    }
}
