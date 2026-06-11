<?php

namespace Tests\Feature;

use App\Models\AccessTier;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LessonVideoPlaybackTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_cannot_save_lesson_video_id_that_is_not_found_in_configured_bunny_library(): void
    {
        config()->set('bunny.stream.library_id', '47630');
        config()->set('bunny.stream.access_key', 'test-access-key');
        config()->set('bunny.stream.cdn_base_url', 'https://vz-example.b-cdn.net');

        Http::fake([
            'https://video.bunnycdn.com/library/47630/videos/*' => Http::response([], 404),
        ]);

        $admin = User::factory()->admin()->create();
        $tier = AccessTier::factory()->create();
        $module = Module::factory()->create();
        $module->accessTiers()->sync([$tier->id]);

        $response = $this->actingAs($admin)->post(route('admin.lessons.store'), [
            'module_id' => $module->id,
            'access_tier_ids' => [$tier->id],
            'assessment_id' => null,
            'title' => 'Playback Validation Lesson',
            'thumbnail' => UploadedFile::fake()->image('lesson-thumbnail.jpg'),
            'workbook' => null,
            'audio' => null,
            'lesson_video_id' => 'd512cc69-beb7-41b7-8d3d-5d07f573f424',
            'content' => '<p>Lesson content</p>',
        ]);

        $response->assertSessionHasErrors('lesson_video_id');
        $this->assertDatabaseMissing('lessons', [
            'title' => 'Playback Validation Lesson',
        ]);
    }

    public function test_student_lesson_page_shows_warning_when_bunny_video_id_is_missing_from_library(): void
    {
        config()->set('bunny.stream.library_id', '47630');
        config()->set('bunny.stream.access_key', 'test-access-key');
        config()->set('bunny.stream.cdn_base_url', 'https://vz-example.b-cdn.net');

        Http::fake([
            'https://video.bunnycdn.com/library/47630/videos/*' => Http::response([], 404),
        ]);

        $tier = AccessTier::factory()->create();
        $student = User::factory()->student()->completeProfile()->create([
            'access_tier_id' => $tier->id,
        ]);

        $module = Module::factory()->create();
        $module->accessTiers()->sync([$tier->id]);

        $lesson = Lesson::factory()->create([
            'module_id' => $module->id,
            'lesson_video_id' => 'd512cc69-beb7-41b7-8d3d-5d07f573f424',
            'audio_url' => null,
            'workbook' => null,
        ]);
        $lesson->accessTiers()->sync([$tier->id]);

        $this->actingAs($student)
            ->get(route('lessons.show', $lesson))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Student/Lessons/Show')
                ->where('lesson.video.video_id', 'd512cc69-beb7-41b7-8d3d-5d07f573f424')
                ->where('lesson.video.hls_url', null)
                ->where('lesson.video.is_ready', false)
                ->where('lesson.video.is_found_in_library', false)
                ->where('lesson.video.warning_message', 'This lesson video ID is valid in format, but it was not found in the configured Bunny Stream library. Please update the lesson with the correct Bunny Stream video GUID from admin.'));
    }

    public function test_student_lesson_page_receives_hls_url_when_bunny_video_exists_in_library(): void
    {
        config()->set('bunny.stream.library_id', '47630');
        config()->set('bunny.stream.access_key', 'test-access-key');
        config()->set('bunny.stream.cdn_base_url', 'https://vz-example.b-cdn.net');

        Http::fake([
            'https://video.bunnycdn.com/library/47630/videos/*' => Http::response([
                'guid' => 'd512cc69-beb7-41b7-8d3d-5d07f573f424',
                'title' => 'Lesson Flow',
            ], 200),
        ]);

        $tier = AccessTier::factory()->create();
        $student = User::factory()->student()->completeProfile()->create([
            'access_tier_id' => $tier->id,
        ]);

        $module = Module::factory()->create();
        $module->accessTiers()->sync([$tier->id]);

        $lesson = Lesson::factory()->create([
            'module_id' => $module->id,
            'lesson_video_id' => 'd512cc69-beb7-41b7-8d3d-5d07f573f424',
            'audio_url' => null,
            'workbook' => null,
        ]);
        $lesson->accessTiers()->sync([$tier->id]);

        $this->actingAs($student)
            ->get(route('lessons.show', $lesson))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Student/Lessons/Show')
                ->where('lesson.video.hls_url', 'https://vz-example.b-cdn.net/d512cc69-beb7-41b7-8d3d-5d07f573f424/playlist.m3u8')
                ->where('lesson.video.is_ready', true)
                ->where('lesson.video.is_found_in_library', true)
                ->where('lesson.video.warning_message', null));
    }
}
