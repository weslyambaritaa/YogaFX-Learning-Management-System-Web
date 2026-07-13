<?php

namespace Tests\Feature\Mobile;

use App\Models\AccessTier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileProfileUpgradeOptionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_profile_returns_dynamic_upgrade_options_for_student(): void
    {
        $starterTier = AccessTier::factory()->create([
            'name' => 'Starter Kit',
            'slug' => 'starter_kit',
            'level' => 1,
            'is_active' => true,
        ]);
        $onlineTier = AccessTier::factory()->create([
            'name' => 'Online',
            'slug' => 'online',
            'level' => 2,
            'is_active' => true,
        ]);
        $masterTier = AccessTier::factory()->create([
            'name' => 'Master Class',
            'slug' => 'master_class',
            'level' => 3,
            'is_active' => true,
        ]);

        $student = User::factory()
            ->student()
            ->completeProfile()
            ->create([
                'access_tier_id' => $starterTier->id,
                'is_active' => true,
            ]);

        Sanctum::actingAs($student);

        $this->getJson('/api/mobile/v1/profile')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.access_tier.slug', 'starter_kit')
            ->assertJsonPath('data.upgrade_options.0.id', $onlineTier->id)
            ->assertJsonPath('data.upgrade_options.0.slug', 'online')
            ->assertJsonPath('data.upgrade_options.1.id', $masterTier->id)
            ->assertJsonPath('data.upgrade_options.1.slug', 'master_class')
            ->assertJsonPath('data.upgrade_options.0.upgrade_url', route('student.upgrades.show', $onlineTier))
            ->assertJsonPath('data.upgrade_options.1.upgrade_url', route('student.upgrades.show', $masterTier));
    }
}
