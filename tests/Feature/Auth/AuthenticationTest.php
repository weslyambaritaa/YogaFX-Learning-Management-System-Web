<?php

namespace Tests\Feature\Auth;

use App\Mail\TemplatedNotificationMail;
use App\Models\AuthEmailOtpChallenge;
use App\Models\User;
use App\Services\EmailOtpChallengeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_are_redirected_to_the_email_otp_screen_after_valid_login_credentials(): void
    {
        $user = User::factory()->student()->completeProfile()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $challenge = AuthEmailOtpChallenge::query()->first();

        $this->assertGuest();
        $this->assertNotNull($challenge);
        $response->assertRedirectContains('/verify-email-otp/');
        Mail::assertSent(TemplatedNotificationMail::class);
    }

    public function test_students_with_incomplete_profiles_are_redirected_to_profile_completion_after_valid_otp(): void
    {
        $user = User::factory()->student()->create();
        $challenge = app(EmailOtpChallengeService::class)->createForLogin($user);

        $response = $this->post(route('auth.otp.verify', [
            'token' => $challenge['token'],
        ], absolute: false), [
            'otp_code' => $challenge['otp_code'],
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('profile.edit', absolute: false));
    }

    public function test_admin_users_are_redirected_to_the_admin_dashboard_after_valid_otp(): void
    {
        $user = User::factory()->admin()->create();
        $challenge = app(EmailOtpChallengeService::class)->createForLogin($user);

        $response = $this->post(route('auth.otp.verify', [
            'token' => $challenge['token'],
        ], absolute: false), [
            'otp_code' => $challenge['otp_code'],
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('admin.dashboard', absolute: false));
    }

    public function test_complete_student_users_are_redirected_to_dashboard_after_valid_otp(): void
    {
        $user = User::factory()->student()->completeProfile()->create();
        $challenge = app(EmailOtpChallengeService::class)->createForLogin($user);

        $response = $this->post(route('auth.otp.verify', [
            'token' => $challenge['token'],
        ], absolute: false), [
            'otp_code' => $challenge['otp_code'],
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('student.dashboard', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect(route('login'));
    }

    public function test_student_users_cannot_access_admin_dashboard(): void
    {
        $user = User::factory()->student()->create();

        $response = $this->actingAs($user)->get(route('admin.dashboard'));

        $response->assertForbidden();
    }

    public function test_admin_users_cannot_access_student_dashboard(): void
    {
        $user = User::factory()->admin()->create();

        $response = $this->actingAs($user)->get(route('student.dashboard'));

        $response->assertForbidden();
    }
}
