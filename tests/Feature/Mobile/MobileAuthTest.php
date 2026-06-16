<?php

namespace Tests\Feature\Mobile;

use App\Models\AccessTier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_login_and_receive_a_mobile_token(): void
    {
        $tier = AccessTier::factory()->create([
            'name' => 'Online',
            'slug' => 'online',
        ]);

        $student = User::factory()
            ->student()
            ->completeProfile()
            ->create([
                'access_tier_id' => $tier->id,
                'is_active' => true,
            ]);

        $response = $this->postJson('/api/mobile/v1/auth/login', [
            'email' => $student->email,
            'password' => 'password',
            'device_name' => 'Pixel 9',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Login successful.')
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.id', $student->id)
            ->assertJsonPath('data.user.access_tier.slug', 'online');

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_mobile_login_rejects_invalid_credentials(): void
    {
        $student = User::factory()->student()->create();

        $this->postJson('/api/mobile/v1/auth/login', [
            'email' => $student->email,
            'password' => 'wrong-password',
        ])->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.email.0', trans('auth.failed'));
    }

    public function test_mobile_login_rejects_admin_accounts(): void
    {
        $admin = User::factory()->admin()->create();

        $this->postJson('/api/mobile/v1/auth/login', [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertForbidden()
            ->assertJson([
                'success' => false,
                'message' => 'This mobile API is only available to student accounts.',
                'errors' => [],
            ]);
    }

    public function test_mobile_login_rejects_inactive_students(): void
    {
        $student = User::factory()->student()->create([
            'is_active' => false,
        ]);

        $this->postJson('/api/mobile/v1/auth/login', [
            'email' => $student->email,
            'password' => 'password',
        ])->assertForbidden()
            ->assertJson([
                'success' => false,
                'message' => 'Your student account is inactive.',
                'errors' => [],
            ]);
    }

    public function test_authenticated_student_can_use_mobile_me_and_logout(): void
    {
        $tier = AccessTier::factory()->create([
            'name' => 'Master Class',
            'slug' => 'master_class',
        ]);

        $student = User::factory()
            ->student()
            ->completeProfile()
            ->create([
                'access_tier_id' => $tier->id,
                'is_active' => true,
            ]);

        $token = $student->createToken('iPhone 17')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/mobile/v1/me')
            ->assertOk()
            ->assertJsonPath('data.id', $student->id);

        $this->withToken($token)
            ->postJson('/api/mobile/v1/auth/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Logout successful.');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_mobile_logout_requires_authentication(): void
    {
        $this->postJson('/api/mobile/v1/auth/logout')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');
    }
}
