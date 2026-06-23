<?php

namespace Tests\Feature\Mobile;

use App\Models\AccessTier;
use App\Models\Module;
use App\Models\User;
use App\Support\MobileSignedUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileSignedMediaTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_content_image_signed_url_is_accepted_for_authorized_student(): void
    {
        [$student, $tier] = $this->createActiveStudentWithTier();

        $module = Module::factory()->create([
            'thumbnail' => 'https://example.com/test-thumbnail.jpg',
        ]);
        $module->accessTiers()->sync([$tier->id]);

        $url = MobileSignedUrl::temporarySignedRoute(
            'mobile.api.v1.content-images.show',
            now()->addHour(),
            [
                'entity' => 'module',
                'id' => $module->id,
                'field' => 'thumbnail',
                'student' => $student->id,
            ],
        );

        $this->get($url)
            ->assertRedirect('https://example.com/test-thumbnail.jpg');
    }

    public function test_mobile_content_image_signed_url_rejects_tampered_signature(): void
    {
        [$student, $tier] = $this->createActiveStudentWithTier();

        $module = Module::factory()->create([
            'thumbnail' => 'https://example.com/test-thumbnail.jpg',
        ]);
        $module->accessTiers()->sync([$tier->id]);

        $validUrl = MobileSignedUrl::temporarySignedRoute(
            'mobile.api.v1.content-images.show',
            now()->addHour(),
            [
                'entity' => 'module',
                'id' => $module->id,
                'field' => 'thumbnail',
                'student' => $student->id,
            ],
        );

        $tamperedUrl = preg_replace('/signature=[^&]+/', 'signature=invalid-signature', $validUrl);

        $this->get($tamperedUrl)
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Invalid signature.');
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
