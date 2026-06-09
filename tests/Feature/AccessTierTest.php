<?php

namespace Tests\Feature;

use App\Models\AccessTier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessTierTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_access_tier_index(): void
    {
        $admin = User::factory()->admin()->create();
        $accessTier = AccessTier::factory()->create([
            'name' => 'Online',
            'slug' => 'online',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.access-tiers.index'));

        $response->assertOk();
        $response->assertSee($accessTier->name);
    }

    public function test_admin_can_create_access_tier(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post(route('admin.access-tiers.store'), [
            'name' => 'Master Class',
            'slug' => 'master_class',
            'description' => 'Advanced tier for premium learning access.',
            'is_active' => true,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('access_tiers', [
            'name' => 'Master Class',
            'slug' => 'master_class',
            'is_active' => true,
        ]);
    }

    public function test_admin_can_update_access_tier(): void
    {
        $admin = User::factory()->admin()->create();
        $accessTier = AccessTier::factory()->create([
            'name' => 'Starter Kit',
            'slug' => 'starter_kit',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->patch(route('admin.access-tiers.update', $accessTier), [
            'name' => 'Starter Kit Updated',
            'slug' => 'starter_kit_updated',
            'description' => 'Updated starter tier description.',
            'is_active' => false,
        ]);

        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('access_tiers', [
            'id' => $accessTier->id,
            'name' => 'Starter Kit Updated',
            'slug' => 'starter_kit_updated',
            'is_active' => false,
        ]);
    }

    public function test_admin_can_delete_unused_access_tier(): void
    {
        $admin = User::factory()->admin()->create();
        $accessTier = AccessTier::factory()->create();

        $response = $this->actingAs($admin)->delete(route('admin.access-tiers.destroy', $accessTier));

        $response->assertRedirect(route('admin.access-tiers.index'));
        $this->assertDatabaseMissing('access_tiers', [
            'id' => $accessTier->id,
        ]);
    }

    public function test_admin_cannot_delete_access_tier_that_is_assigned_to_student(): void
    {
        $admin = User::factory()->admin()->create();
        $accessTier = AccessTier::factory()->create();
        User::factory()->student()->create([
            'access_tier_id' => $accessTier->id,
        ]);

        $response = $this->actingAs($admin)->delete(route('admin.access-tiers.destroy', $accessTier));

        $response->assertSessionHasErrors('access_tier');
        $this->assertDatabaseHas('access_tiers', [
            'id' => $accessTier->id,
        ]);
    }

    public function test_student_cannot_access_admin_access_tier_routes(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($student)
            ->get(route('admin.access-tiers.index'))
            ->assertForbidden();
    }
}
