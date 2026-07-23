<?php

namespace Tests\Feature;

use App\Models\LinkControlSetting;
use App\Models\User;
use App\Services\AppDownloadQrService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery\MockInterface;
use Tests\TestCase;

class LinkControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_app_download_page_uses_latest_store_links(): void
    {
        LinkControlSetting::query()->create([
            'id' => 1,
            'google_play_url' => 'https://play.google.com/store/apps/details?id=com.yogafx.app',
            'app_store_url' => 'https://apps.apple.com/id/app/yogafx/id123456789',
        ]);

        $response = $this->get(route('public.app-download'));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Public/AppDownload')
            ->where('appDownload.google_play_url', 'https://play.google.com/store/apps/details?id=com.yogafx.app')
            ->where('appDownload.app_store_url', 'https://apps.apple.com/id/app/yogafx/id123456789')
            ->where('appDownload.has_any_link', true)
            ->where(
                'appDownload.download_page_url',
                fn (string $value) => str_ends_with($value, '/download-app')
            ));
    }

    public function test_admin_can_update_link_control_and_save_generated_qr_path(): void
    {
        $admin = User::factory()->admin()->create();
        LinkControlSetting::query()->create([
            'id' => 1,
            'qr_image' => null,
            'google_play_url' => null,
            'app_store_url' => null,
        ]);

        $this->mock(AppDownloadQrService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('regenerate')
                ->once()
                ->with(null)
                ->andReturn([
                    'path' => 'bunny://link-control/qr-images/app-download.svg',
                    'warning' => null,
                    'used_local_fallback' => false,
                ]);

            $mock->shouldReceive('publicUrl')
                ->zeroOrMoreTimes()
                ->andReturn('http://localhost/download-app');
        });

        $response = $this->actingAs($admin)->patch(route('admin.link-control.update'), [
            'google_play_url' => 'https://play.google.com/store/apps/details?id=com.yogafx.app',
            'app_store_url' => 'https://apps.apple.com/id/app/yogafx/id123456789',
        ]);

        $response->assertRedirect(route('admin.link-control.show'));
        $response->assertSessionHas('status', 'link-control-updated');

        $this->assertDatabaseHas('link_control_settings', [
            'id' => 1,
            'google_play_url' => 'https://play.google.com/store/apps/details?id=com.yogafx.app',
            'app_store_url' => 'https://apps.apple.com/id/app/yogafx/id123456789',
            'qr_image' => 'bunny://link-control/qr-images/app-download.svg',
        ]);
    }
}
