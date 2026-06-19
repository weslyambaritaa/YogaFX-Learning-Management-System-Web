<?php

namespace Tests\Feature\Auth;

use App\Mail\TemplatedNotificationMail;
use App\Models\StudentPasswordChangeRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        $this->get('/forgot-password')->assertOk();
    }

    public function test_web_forgot_password_creates_unified_password_change_request_and_sends_unified_email(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'reset-user@yogafx.test',
        ]);

        $this->post('/forgot-password', ['email' => $user->email])
            ->assertSessionHas('status');

        $request = StudentPasswordChangeRequest::query()->where('user_id', $user->id)->first();

        $this->assertNotNull($request);
        $this->assertSame(StudentPasswordChangeRequest::ORIGIN_WEB, $request->origin);

        Mail::assertSent(TemplatedNotificationMail::class, function (TemplatedNotificationMail $mail) {
            return str_contains($mail->bodyHtml, '/profile/password/change/')
                && preg_match('/\b\d{6}\b/', $mail->bodyHtml) === 1;
        });
    }

    public function test_legacy_reset_password_route_redirects_into_unified_password_change_flow(): void
    {
        $this->get('/reset-password/legacy-token?email=legacy@yogafx.test')
            ->assertRedirect('/profile/password/change/legacy-token?email=legacy%40yogafx.test');
    }

    public function test_password_can_be_reset_through_unified_password_change_flow(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'reset-success@yogafx.test',
        ]);

        $this->post('/forgot-password', ['email' => $user->email])
            ->assertSessionHas('status');

        [$changeUrl, $otpCode] = $this->extractChangePasswordLinkAndOtpFromMail();
        parse_str((string) parse_url($changeUrl, PHP_URL_QUERY), $query);
        $token = basename((string) parse_url($changeUrl, PHP_URL_PATH));

        $this->get($changeUrl)->assertOk();

        $this->post('/profile/password/change', [
            'token' => $token,
            'email' => $query['email'] ?? null,
            'otp_code' => $otpCode,
            'new_password' => 'password',
            'new_password_confirmation' => 'password',
        ])->assertRedirect(route('login'));

        $this->assertNotNull(StudentPasswordChangeRequest::query()->where('user_id', $user->id)->first()?->used_at);
        $this->assertCredentials([
            'email' => $user->email,
            'password' => 'password',
        ]);
    }

    public function test_mobile_forgot_password_uses_the_same_unified_change_password_link_and_otp_request(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'mobile-reset@yogafx.test',
        ]);

        $this->postJson('/api/mobile/v1/auth/forgot-password', [
            'email' => $user->email,
        ])->assertOk();

        $request = StudentPasswordChangeRequest::query()->where('user_id', $user->id)->first();

        $this->assertNotNull($request);
        $this->assertSame(StudentPasswordChangeRequest::ORIGIN_MOBILE, $request->origin);

        Mail::assertSent(TemplatedNotificationMail::class, function (TemplatedNotificationMail $mail) {
            return str_contains($mail->bodyHtml, '/profile/password/change/')
                && preg_match('/\b\d{6}\b/', $mail->bodyHtml) === 1;
        });
    }

    public function test_student_profile_password_request_uses_the_same_unified_change_password_link_and_otp_request(): void
    {
        Mail::fake();

        $student = User::factory()->student()->completeProfile()->create([
            'email' => 'student-reset@yogafx.test',
        ]);

        $this->actingAs($student)
            ->post('/profile/password/request')
            ->assertRedirect(route('profile.edit'));

        $request = StudentPasswordChangeRequest::query()->where('user_id', $student->id)->first();

        $this->assertNotNull($request);
        $this->assertSame(StudentPasswordChangeRequest::ORIGIN_WEB, $request->origin);

        Mail::assertSent(TemplatedNotificationMail::class, function (TemplatedNotificationMail $mail) {
            return str_contains($mail->bodyHtml, '/profile/password/change/')
                && preg_match('/\b\d{6}\b/', $mail->bodyHtml) === 1;
        });
    }

    public function test_mobile_reset_endpoint_uses_the_same_otp_backed_backend_flow(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'mobile-submit@yogafx.test',
        ]);

        $this->post('/forgot-password', ['email' => $user->email])
            ->assertSessionHas('status');

        [$changeUrl, $otpCode] = $this->extractChangePasswordLinkAndOtpFromMail();
        parse_str((string) parse_url($changeUrl, PHP_URL_QUERY), $query);
        $token = basename((string) parse_url($changeUrl, PHP_URL_PATH));

        $this->postJson('/api/mobile/v1/auth/reset-password', [
            'token' => $token,
            'email' => $query['email'] ?? null,
            'otp_code' => $otpCode,
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertOk();

        $this->assertCredentials([
            'email' => $user->email,
            'password' => 'password',
        ]);
    }

    public function test_mobile_origin_web_submit_redirects_to_mobile_success_landing(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'mobile-web-success@yogafx.test',
        ]);

        $this->postJson('/api/mobile/v1/auth/forgot-password', [
            'email' => $user->email,
        ])->assertOk();

        [$changeUrl, $otpCode] = $this->extractChangePasswordLinkAndOtpFromMail();
        parse_str((string) parse_url($changeUrl, PHP_URL_QUERY), $query);
        $token = basename((string) parse_url($changeUrl, PHP_URL_PATH));

        $this->post('/profile/password/change', [
            'token' => $token,
            'email' => $query['email'] ?? null,
            'otp_code' => $otpCode,
            'new_password' => 'password',
            'new_password_confirmation' => 'password',
        ])->assertRedirect(route('password.success.mobile'));
    }

    public function test_mobile_success_landing_contains_manual_return_instruction(): void
    {
        $response = $this->get(route('password.success.mobile'));

        $response->assertOk();
        $response->assertSee('Password updated successfully');
        $response->assertSee('Please return to the YogaFX mobile app manually');
        $response->assertSee('sign in again with your new password');
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function extractChangePasswordLinkAndOtpFromMail(): array
    {
        $mail = Mail::sent(TemplatedNotificationMail::class)->first();

        $this->assertInstanceOf(TemplatedNotificationMail::class, $mail);

        preg_match('/https?:\/\/[^\s"\']+\/profile\/password\/change\/[^\s"\']+/', $mail->bodyHtml, $urlMatches);
        preg_match('/\b(\d{6})\b/', $mail->bodyHtml, $otpMatches);

        $this->assertNotEmpty($urlMatches[0] ?? null);
        $this->assertNotEmpty($otpMatches[1] ?? null);

        return [$urlMatches[0], $otpMatches[1]];
    }
}
