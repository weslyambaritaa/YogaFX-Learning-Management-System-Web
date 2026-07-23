<?php

namespace Tests\Feature\Mobile;

use App\Models\AccessTier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileApiFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_me_endpoint_requires_authentication(): void
    {
        $this->getJson('/api/mobile/v1/me')
            ->assertUnauthorized()
            ->assertJson([
                'success' => false,
                'message' => 'Unauthenticated.',
                'errors' => [],
            ]);
    }

    public function test_student_can_access_mobile_me_endpoint(): void
    {
        $tier = AccessTier::factory()->create([
            'name' => 'Online',
            'slug' => 'online',
            'level' => 2,
        ]);
        $upgradeTier = AccessTier::factory()->create([
            'name' => 'Master Class',
            'slug' => 'master_class',
            'level' => 3,
            'is_active' => true,
        ]);

        $student = User::factory()
            ->student()
            ->completeProfile()
            ->create([
                'access_tier_id' => $tier->id,
                'is_active' => true,
            ]);

        Sanctum::actingAs($student);

        $this->getJson('/api/mobile/v1/me')
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Authenticated student retrieved successfully.',
                'data' => [
                    'id' => $student->id,
                    'email' => $student->email,
                    'role' => User::ROLE_STUDENT,
                    'profile_completed' => true,
                    'access_tier' => [
                        'id' => $tier->id,
                        'name' => 'Online',
                        'slug' => 'online',
                    ],
                ],
            ])
            ->assertJsonPath('data.upgrade_options.0.id', $upgradeTier->id)
            ->assertJsonPath('data.upgrade_options.0.slug', 'master_class');
    }

    public function test_admin_cannot_access_mobile_student_endpoint(): void
    {
        $admin = User::factory()->admin()->create();

        Sanctum::actingAs($admin);

        $this->getJson('/api/mobile/v1/me')
            ->assertForbidden()
            ->assertJson([
                'success' => false,
                'message' => 'This mobile API is only available to student accounts.',
                'errors' => [],
            ]);
    }

    public function test_inactive_student_cannot_access_mobile_student_endpoint(): void
    {
        $student = User::factory()->student()->create([
            'is_active' => false,
        ]);

        Sanctum::actingAs($student);

        $this->getJson('/api/mobile/v1/me')
            ->assertForbidden()
            ->assertJson([
                'success' => false,
                'message' => 'Your student account is inactive.',
                'errors' => [],
            ]);
    }
}
