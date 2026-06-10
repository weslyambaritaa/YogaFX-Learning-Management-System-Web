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

    public function test_module_thumbnail_url_changes_after_thumbnail_update(): void
    {
        Storage::fake('local');

        $admin = User::factory()->admin()->create();
        $tier = AccessTier::factory()->create();
        $module = Module::factory()->create([
            'thumbnail' => UploadedFile::fake()->image('initial-module.jpg')->store('modules/thumbnails', 'local'),
        ]);
        $module->accessTiers()->sync([$tier->id]);

        $initialThumbnailUrl = $this->protectedMediaUrl(
            'module',
            $module->id,
            'thumbnail',
            $module->thumbnail,
            $module->updated_at,
        );

        $this->actingAs($admin)
            ->get(route('admin.modules.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Modules/Index')
                ->where('modules.0.thumbnail_url', $initialThumbnailUrl));

        $response = $this->actingAs($admin)->patch(route('admin.modules.update', $module), [
            'title' => $module->title,
            'url_slug' => $module->url_slug,
            'thumbnail' => UploadedFile::fake()->image('updated-module.jpg'),
            'access_tier_ids' => [$tier->id],
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('admin.modules.index'));

        $module->refresh();

        $updatedThumbnailUrl = $this->protectedMediaUrl(
            'module',
            $module->id,
            'thumbnail',
            $module->thumbnail,
            $module->updated_at,
        );

        $this->assertNotSame($initialThumbnailUrl, $updatedThumbnailUrl);

        $this->actingAs($admin)
            ->get(route('admin.modules.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Modules/Index')
                ->where('modules.0.thumbnail_url', $updatedThumbnailUrl));
    }

    public function test_admin_cannot_create_module_with_thumbnail_larger_than_10mb(): void
    {
        Storage::fake('local');

        $admin = User::factory()->admin()->create();
        $tier = AccessTier::factory()->create();

        $response = $this->actingAs($admin)->post(route('admin.modules.store'), [
            'title' => 'Large Module',
            'url_slug' => 'large-module',
            'thumbnail' => UploadedFile::fake()->image('large-module.jpg')->size(10241),
            'access_tier_ids' => [$tier->id],
        ]);

        $response->assertSessionHasErrors('thumbnail');
        $this->assertDatabaseMissing('modules', ['url_slug' => 'large-module']);
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

    public function test_admin_can_create_lesson_with_svg_thumbnail(): void
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
            'title' => 'SVG Thumbnail Lesson',
            'thumbnail' => $this->svgUpload('lesson-thumbnail.svg'),
            'workbook' => null,
            'video' => 'https://example.com/video',
            'audio' => null,
            'content' => '<p>Lesson content</p>',
        ]);

        $response->assertSessionHasNoErrors();

        $lesson = Lesson::query()->where('title', 'SVG Thumbnail Lesson')->first();

        $this->assertNotNull($lesson);
        Storage::disk('local')->assertExists($lesson->thumbnail);
    }

    public function test_admin_lesson_index_includes_thumbnail_url_for_uploaded_thumbnail(): void
    {
        Storage::fake('local');

        $admin = User::factory()->admin()->create();
        $tier = AccessTier::factory()->create();
        $module = Module::factory()->create();
        $module->accessTiers()->sync([$tier->id]);

        $lesson = Lesson::factory()->create([
            'module_id' => $module->id,
            'thumbnail' => UploadedFile::fake()->image('lesson-index-thumbnail.jpg')
                ->store('lessons/thumbnails', 'local'),
        ]);
        $lesson->accessTiers()->sync([$tier->id]);

        $expectedThumbnailUrl = $this->protectedMediaUrl(
            'lesson',
            $lesson->id,
            'thumbnail',
            $lesson->thumbnail,
            $lesson->updated_at,
        );

        $this->actingAs($admin)
            ->get(route('admin.lessons.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Lessons/Index')
                ->where('lessons.0.thumbnail_url', $expectedThumbnailUrl));
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

    public function test_admin_can_update_lesson_with_svg_thumbnail(): void
    {
        Storage::fake('local');

        $admin = User::factory()->admin()->create();
        $tier = AccessTier::factory()->create();
        $module = Module::factory()->create();
        $module->accessTiers()->sync([$tier->id]);

        $lesson = Lesson::factory()->create([
            'module_id' => $module->id,
            'thumbnail' => UploadedFile::fake()->image('existing-thumbnail.jpg')
                ->store('lessons/thumbnails', 'local'),
        ]);
        $lesson->accessTiers()->sync([$tier->id]);

        $oldThumbnail = $lesson->thumbnail;

        $response = $this->actingAs($admin)->patch(route('admin.lessons.update', $lesson), [
            'module_id' => $module->id,
            'access_tier_ids' => [$tier->id],
            'assessment_id' => null,
            'title' => $lesson->title,
            'thumbnail' => $this->svgUpload('updated-thumbnail.svg'),
            'workbook' => null,
            'video' => 'https://example.com/video',
            'audio' => null,
            'content' => '<p>Updated content</p>',
        ]);

        $response->assertSessionHasNoErrors();

        $lesson->refresh();

        $this->assertNotSame($oldThumbnail, $lesson->thumbnail);
        Storage::disk('local')->assertMissing($oldThumbnail);
        Storage::disk('local')->assertExists($lesson->thumbnail);
    }

    public function test_admin_cannot_create_lesson_with_file_larger_than_10mb(): void
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
            'title' => 'Oversized Lesson',
            'thumbnail' => UploadedFile::fake()->image('large-lesson.jpg')->size(10241),
            'workbook' => UploadedFile::fake()->create('large-workbook.pdf', 10241, 'application/pdf'),
            'video' => 'https://example.com/video',
            'audio' => null,
            'content' => '<p>Oversized upload</p>',
        ]);

        $response->assertSessionHasErrors(['thumbnail', 'workbook']);
        $this->assertDatabaseMissing('lessons', ['title' => 'Oversized Lesson']);
    }

    public function test_admin_cannot_create_lesson_with_invalid_thumbnail_file(): void
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
            'title' => 'Invalid Thumbnail Lesson',
            'thumbnail' => UploadedFile::fake()->create('not-an-image.txt', 10, 'text/plain'),
            'workbook' => null,
            'video' => 'https://example.com/video',
            'audio' => null,
            'content' => '<p>Lesson content</p>',
        ]);

        $response->assertSessionHasErrors('thumbnail');
        $response->assertSessionHasErrors([
            'thumbnail' => 'The thumbnail must be a valid image file in a supported format, such as JPG, PNG, GIF, BMP, WebP, SVG, AVIF, HEIC, or HEIF.',
        ]);
        $this->assertDatabaseMissing('lessons', ['title' => 'Invalid Thumbnail Lesson']);
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

    public function test_admin_cannot_create_ebook_with_file_larger_than_10mb(): void
    {
        Storage::fake('local');

        $admin = User::factory()->admin()->create();
        $tier = AccessTier::factory()->create();

        $response = $this->actingAs($admin)->post(route('admin.ebooks.store'), [
            'title' => 'Oversized Ebook',
            'file' => UploadedFile::fake()->create('oversized-ebook.pdf', 10241, 'application/pdf'),
            'access_tier_ids' => [$tier->id],
        ]);

        $response->assertSessionHasErrors('file');
        $this->assertDatabaseMissing('ebooks', ['title' => 'Oversized Ebook']);
    }

    public function test_admin_edit_lesson_includes_pdf_workbook_preview_data(): void
    {
        Storage::fake('local');

        $admin = User::factory()->admin()->create();
        $tier = AccessTier::factory()->create();
        $module = Module::factory()->create();
        $module->accessTiers()->sync([$tier->id]);

        $path = 'lessons/workbooks/previewable-workbook.pdf';
        Storage::disk('local')->put($path, 'sample workbook pdf content');

        $lesson = Lesson::factory()->create([
            'module_id' => $module->id,
            'title' => 'Workbook Preview Lesson',
            'workbook' => $path,
        ]);
        $lesson->accessTiers()->sync([$tier->id]);

        $this->actingAs($admin)
            ->get(route('admin.lessons.edit', $lesson))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Lessons/Edit')
                ->where('lesson.workbook_preview.title', 'Workbook Preview Lesson Workbook')
                ->where('lesson.workbook_preview.preview_supported', true)
                ->where('lesson.workbook_preview.download_url', $this->protectedMediaUrl(
                    'lesson',
                    $lesson->id,
                    'workbook',
                    $lesson->workbook,
                    $lesson->updated_at,
                    true,
                ))
                ->where('lesson.workbook_preview.preview_url', $this->protectedMediaUrl(
                    'lesson',
                    $lesson->id,
                    'workbook',
                    $lesson->workbook,
                    $lesson->updated_at,
                    false,
                    ['inline' => 1],
                )));
    }

    public function test_admin_edit_lesson_shows_fallback_message_for_non_previewable_workbook_file(): void
    {
        Storage::fake('local');

        $admin = User::factory()->admin()->create();
        $tier = AccessTier::factory()->create();
        $module = Module::factory()->create();
        $module->accessTiers()->sync([$tier->id]);

        $path = 'lessons/workbooks/reference-workbook.docx';
        Storage::disk('local')->put($path, 'sample docx content');

        $lesson = Lesson::factory()->create([
            'module_id' => $module->id,
            'title' => 'Reference Workbook Lesson',
            'workbook' => $path,
        ]);
        $lesson->accessTiers()->sync([$tier->id]);

        $this->actingAs($admin)
            ->get(route('admin.lessons.edit', $lesson))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Lessons/Edit')
                ->where('lesson.workbook_preview.preview_supported', false)
                ->where('lesson.workbook_preview.preview_message', 'This workbook file cannot be previewed in the browser yet. You can still download it.'));
    }

    public function test_lesson_create_and_edit_pages_share_fixed_10mb_upload_constraints(): void
    {
        $admin = User::factory()->admin()->create();
        $tier = AccessTier::factory()->create();
        $module = Module::factory()->create();
        $module->accessTiers()->sync([$tier->id]);
        $lesson = Lesson::factory()->create([
            'module_id' => $module->id,
        ]);
        $lesson->accessTiers()->sync([$tier->id]);

        $expectedConstraints = [
            'max_size_bytes' => 10 * 1024 * 1024,
            'max_size_label' => '10 MB',
        ];

        $this->actingAs($admin)
            ->get(route('admin.lessons.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Lessons/Create')
                ->where('uploadConstraints', $expectedConstraints));

        $this->actingAs($admin)
            ->get(route('admin.lessons.edit', $lesson))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Lessons/Edit')
                ->where('uploadConstraints', $expectedConstraints));
    }

    public function test_admin_cannot_create_course_with_thumbnail_larger_than_10mb(): void
    {
        Storage::fake('local');

        $admin = User::factory()->admin()->create();
        $tier = AccessTier::factory()->create();

        $response = $this->actingAs($admin)->post(route('admin.courses.store'), [
            'title' => 'Oversized Course',
            'url_slug' => 'oversized-course',
            'access_tier_id' => $tier->id,
            'description' => 'Course with invalid thumbnail size.',
            'thumbnail' => UploadedFile::fake()->image('oversized-course.jpg')->size(10241),
            'video' => 'https://example.com/course-video',
        ]);

        $response->assertSessionHasErrors('thumbnail');
        $this->assertDatabaseMissing('courses', ['url_slug' => 'oversized-course']);
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
                ->where('ebook.download_url', $this->protectedMediaUrl(
                    'ebook',
                    $ebook->id,
                    'file',
                    $ebook->file,
                    $ebook->updated_at,
                    true,
                )));
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

    private function protectedMediaUrl(
        string $entity,
        int $id,
        string $field,
        string $path,
        mixed $versionSeed,
        bool $download = false,
        array $extraParameters = [],
    ): string {
        $parameters = [
            'entity' => $entity,
            'id' => $id,
            'field' => $field,
            'v' => sha1(implode('|', [
                $entity,
                $id,
                $field,
                $path,
                Storage::disk('local')->exists($path)
                    ? (string) Storage::disk('local')->lastModified($path)
                    : '',
                (string) $versionSeed,
                json_encode($extraParameters),
            ])),
        ];

        if ($download) {
            $parameters['download'] = 1;
        }

        return route('media.show', array_merge($parameters, $extraParameters));
    }

    private function svgUpload(string $filename): UploadedFile
    {
        $svg = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" width="120" height="80" viewBox="0 0 120 80">
    <rect width="120" height="80" fill="#eef2ff" />
    <text x="60" y="44" text-anchor="middle" font-size="18" fill="#4338ca">YogaFX</text>
</svg>
SVG;

        $path = tempnam(sys_get_temp_dir(), 'svg-thumbnail-');
        file_put_contents($path, $svg);

        return new UploadedFile(
            $path,
            $filename,
            'image/svg+xml',
            null,
            true,
        );
    }
}
