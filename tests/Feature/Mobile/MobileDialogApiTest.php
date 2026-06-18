<?php

namespace Tests\Feature\Mobile;

use App\Models\AccessTier;
use App\Models\DialogContent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileDialogApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_fetch_mobile_dialog_index(): void
    {
        [$student] = $this->createActiveStudentWithTier();

        DialogContent::query()->create([
            'key' => DialogContent::KEY_FULL_STANDING,
            'title' => 'Standing Dialog',
            'content' => '<p>Standing content</p>',
        ]);

        Sanctum::actingAs($student);

        $this->getJson('/api/mobile/v1/dialogs')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Mobile dialogs retrieved successfully.')
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.items.0.key', DialogContent::KEY_FULL_STANDING)
            ->assertJsonPath('data.items.0.title', 'Standing Dialog')
            ->assertJsonPath('data.items.0.content', '<p>Standing content</p>')
            ->assertJsonPath('data.items.0.has_content', true)
            ->assertJsonPath('data.items.1.key', DialogContent::KEY_FULL_FLOOR)
            ->assertJsonPath('data.items.1.title', 'Full Floor Series Dialogue')
            ->assertJsonPath('data.items.1.content', '')
            ->assertJsonPath('data.items.1.has_content', false);
    }

    public function test_student_can_fetch_mobile_dialog_detail_with_fallback_content(): void
    {
        [$student] = $this->createActiveStudentWithTier();

        Sanctum::actingAs($student);

        $this->getJson('/api/mobile/v1/dialogs/full-floor')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Mobile dialog retrieved successfully.')
            ->assertJsonPath('data.key', DialogContent::KEY_FULL_FLOOR)
            ->assertJsonPath('data.title', 'Full Floor Series Dialogue')
            ->assertJsonPath('data.content', '')
            ->assertJsonPath('data.has_content', false);
    }

    public function test_student_gets_not_found_for_unknown_mobile_dialog_key(): void
    {
        [$student] = $this->createActiveStudentWithTier();

        Sanctum::actingAs($student);

        $this->getJson('/api/mobile/v1/dialogs/not-real')
            ->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Dialog not found for the authenticated student.');
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
