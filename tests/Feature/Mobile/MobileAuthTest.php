<?php

namespace Tests\Feature\Mobile;

use App\Models\AccessTier;
use App\Models\AuthEmailOtpChallenge;
use App\Models\User;
use App\Services\EmailOtpChallengeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MobileAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    public function test_student_can_submit_valid_credentials_and_receive_an_otp_challenge(): void
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
            ->assertJsonPath('message', 'OTP code sent to your email.')
            ->assertJsonPath('data.otp_required', true)
            ->assertJsonPath('data.email', $student->email);

        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertDatabaseCount('auth_email_otp_challenges', 1);
        $this->assertDatabaseHas('auth_email_otp_challenges', [
            'user_id' => $student->id,
            'context' => AuthEmailOtpChallenge::CONTEXT_LOGIN,
            'email' => $student->email,
        ]);
    }

    public function test_student_can_request_mobile_login_otp_via_request_otp_alias_route(): void
    {
        $student = User::factory()->student()->completeProfile()->create([
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/mobile/v1/auth/login/request-otp', [
            'email' => $student->email,
            'password' => 'password',
            'device_name' => 'Pixel 9',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'OTP code sent to your email.')
            ->assertJsonPath('data.otp_required', true)
            ->assertJsonPath('data.email', $student->email);

        $this->assertDatabaseHas('auth_email_otp_challenges', [
            'user_id' => $student->id,
            'context' => AuthEmailOtpChallenge::CONTEXT_LOGIN,
            'email' => $student->email,
        ]);
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

    public function test_student_can_verify_mobile_login_otp_and_receive_a_mobile_token(): void
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

        $challenge = app(EmailOtpChallengeService::class)->createForLogin($student, [
            'device_name' => 'Pixel 9',
        ]);

        $response = $this->postJson('/api/mobile/v1/auth/login/verify-otp', [
            'challenge_token' => $challenge['token'],
            'otp_code' => $challenge['otp_code'],
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Login successful.')
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.id', $student->id)
            ->assertJsonPath('data.user.access_tier.slug', 'online');

        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertDatabaseHas('user_sessions', [
            'user_id' => $student->id,
            'session_id' => 'mobile-token:1',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('auth_email_otp_challenges', [
            'user_id' => $student->id,
            'context' => AuthEmailOtpChallenge::CONTEXT_LOGIN,
        ]);
        $this->assertNotNull(AuthEmailOtpChallenge::query()->first()?->used_at);
    }

    public function test_mobile_login_otp_verification_rejects_invalid_code(): void
    {
        $student = User::factory()->student()->completeProfile()->create([
            'is_active' => true,
        ]);
        $challenge = app(EmailOtpChallengeService::class)->createForLogin($student, [
            'device_name' => 'Pixel 9',
        ]);

        $this->postJson('/api/mobile/v1/auth/login/verify-otp', [
            'challenge_token' => $challenge['token'],
            'otp_code' => '000000',
        ])->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath(
                'errors.otp_code.0',
                'The OTP code is invalid. Please check the email that YogaFX sent you and try again.',
            );
    }

    public function test_mobile_login_otp_verification_rejects_expired_challenges(): void
    {
        $student = User::factory()->student()->completeProfile()->create([
            'is_active' => true,
        ]);
        $challenge = app(EmailOtpChallengeService::class)->createForLogin($student, [
            'device_name' => 'Pixel 9',
        ]);

        AuthEmailOtpChallenge::query()->update([
            'expires_at' => now()->subMinute(),
        ]);

        $this->postJson('/api/mobile/v1/auth/login/verify-otp', [
            'challenge_token' => $challenge['token'],
            'otp_code' => $challenge['otp_code'],
        ])->assertStatus(422)
            ->assertJsonPath(
                'errors.otp_code.0',
                'This verification request is invalid or has expired. Please start again.',
            );
    }

    public function test_mobile_login_otp_verification_rejects_reused_challenges(): void
    {
        $student = User::factory()->student()->completeProfile()->create([
            'is_active' => true,
        ]);
        $challenge = app(EmailOtpChallengeService::class)->createForLogin($student, [
            'device_name' => 'Pixel 9',
        ]);

        $this->postJson('/api/mobile/v1/auth/login/verify-otp', [
            'challenge_token' => $challenge['token'],
            'otp_code' => $challenge['otp_code'],
        ])->assertOk();

        $this->postJson('/api/mobile/v1/auth/login/verify-otp', [
            'challenge_token' => $challenge['token'],
            'otp_code' => $challenge['otp_code'],
        ])->assertStatus(422)
            ->assertJsonPath(
                'errors.otp_code.0',
                'This verification request is invalid or has expired. Please start again.',
            );
    }

    public function test_mobile_login_can_resend_otp_and_replace_the_previous_challenge(): void
    {
        $student = User::factory()->student()->completeProfile()->create([
            'is_active' => true,
        ]);
        $firstChallenge = app(EmailOtpChallengeService::class)->createForLogin($student, [
            'device_name' => 'Pixel 9',
        ]);

        $response = $this->postJson('/api/mobile/v1/auth/login/resend-otp', [
            'challenge_token' => $firstChallenge['token'],
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'OTP code sent to your email.')
            ->assertJsonPath('data.otp_required', true)
            ->assertJsonPath('data.email', $student->email);

        $this->assertDatabaseCount('auth_email_otp_challenges', 1);
        $newChallenge = AuthEmailOtpChallenge::query()->first();

        $this->assertNotNull($newChallenge);
        $this->assertNotSame(
            hash('sha256', $firstChallenge['token']),
            $newChallenge->token_hash,
        );
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
                'total_access_duration_seconds' => 120,
            ]);

        $token = $student->createToken('iPhone 17')->plainTextToken;
        \App\Models\UserSession::query()->create([
            'user_id' => $student->id,
            'session_id' => 'mobile-token:1',
            'login_at' => now()->subMinutes(2),
            'last_activity_at' => now()->subMinute(),
            'is_active' => true,
        ]);

        $this->withToken($token)
            ->getJson('/api/mobile/v1/me')
            ->assertOk()
            ->assertJsonPath('data.id', $student->id);

        $this->withToken($token)
            ->postJson('/api/mobile/v1/auth/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Logout successful.');

        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertDatabaseHas('user_sessions', [
            'user_id' => $student->id,
            'session_id' => 'mobile-token:1',
            'is_active' => false,
        ]);
        $this->assertGreaterThanOrEqual(180, $student->fresh()->total_access_duration_seconds);
    }

    public function test_mobile_logout_requires_authentication(): void
    {
        $this->postJson('/api/mobile/v1/auth/logout')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');
    }
}
