<?php

namespace Tests\Feature\Auth;

use App\Models\EmailLog;
use App\Models\StudentPasswordChangeRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertStatus(200);
    }

    public function test_reset_password_link_can_be_requested(): void
    {
        Mail::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('email_logs', [
            'notification_type' => 'reset_password',
            'reference_type' => 'user',
            'reference_id' => $user->id,
            'recipient_email' => $user->email,
            'status' => 'sent',
        ]);

        $emailLog = EmailLog::query()
            ->where('notification_type', 'reset_password')
            ->where('reference_type', 'user')
            ->where('reference_id', $user->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($emailLog);
        $this->assertStringContainsString('one-time password code', strtolower($emailLog->body_snapshot));
    }

    public function test_reset_password_link_uses_configured_public_url(): void
    {
        Mail::fake();

        config()->set('app.url', 'http://127.0.0.1:8000');
        config()->set('app.public_url', 'http://192.168.0.11:8000');

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email])
            ->assertSessionHasNoErrors();

        $emailLog = EmailLog::query()
            ->where('notification_type', 'reset_password')
            ->where('reference_type', 'user')
            ->where('reference_id', $user->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($emailLog);
        $this->assertStringContainsString('http://192.168.0.11:8000/reset-password/', $emailLog->body_snapshot);
        $this->assertStringNotContainsString('http://127.0.0.1:8000/reset-password/', $emailLog->body_snapshot);
    }

    public function test_student_password_change_screen_can_be_rendered_by_guest(): void
    {
        $user = User::factory()->create();
        $token = Password::broker()->createToken($user);

        StudentPasswordChangeRequest::query()->create([
            'user_id' => $user->id,
            'email' => $user->email,
            'token_hash' => hash('sha256', $token),
            'otp_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(60),
        ]);

        $this->get(route('profile.password.change.edit', [
            'token' => $token,
            'email' => $user->email,
        ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/StudentPasswordChange')
                ->where('email', $user->email)
                ->where('token', $token));
    }

    public function test_reset_password_screen_can_be_rendered_by_guest_with_valid_otp_request(): void
    {
        $user = User::factory()->create();
        $token = Password::broker()->createToken($user);

        StudentPasswordChangeRequest::query()->create([
            'user_id' => $user->id,
            'email' => $user->email,
            'token_hash' => hash('sha256', $token),
            'otp_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(60),
        ]);

        $this->get(route('password.reset', [
            'token' => $token,
            'email' => $user->email,
        ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/ResetPassword')
                ->where('email', $user->email)
                ->where('token', $token));
    }

    public function test_student_password_change_request_uses_configured_public_url(): void
    {
        Mail::fake();

        config()->set('app.url', 'http://127.0.0.1:8000');
        config()->set('app.public_url', 'http://192.168.0.11:8000');

        $user = User::factory()->create();
        $this->actingAs($user);

        $this->post(route('profile.password.request'))
            ->assertRedirect(route('profile.edit'));

        $emailLog = EmailLog::query()
            ->where('notification_type', 'reset_password')
            ->where('reference_type', 'student_password_change')
            ->where('reference_id', $user->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($emailLog);
        $this->assertStringContainsString('http://192.168.0.11:8000/profile/password/change/', $emailLog->body_snapshot);
        $this->assertStringNotContainsString('http://127.0.0.1:8000/profile/password/change/', $emailLog->body_snapshot);
    }

    public function test_password_can_be_reset_with_valid_token_and_otp(): void
    {
        $user = User::factory()->create([
            'password' => 'old-password',
        ]);
        $token = Password::broker()->createToken($user);

        StudentPasswordChangeRequest::query()->create([
            'user_id' => $user->id,
            'email' => $user->email,
            'token_hash' => hash('sha256', $token),
            'otp_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(60),
        ]);

        $this->post(route('password.store'), [
            'token' => $token,
            'email' => $user->email,
            'otp_code' => '123456',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('login'));

        $this->assertDatabaseHas('student_password_change_requests', [
            'user_id' => $user->id,
            'email' => $user->email,
        ]);
        $this->assertNotNull(
            StudentPasswordChangeRequest::query()->where('user_id', $user->id)->first()?->used_at
        );
    }

    public function test_password_cannot_be_reset_with_invalid_otp(): void
    {
        $user = User::factory()->create();
        $token = Password::broker()->createToken($user);

        StudentPasswordChangeRequest::query()->create([
            'user_id' => $user->id,
            'email' => $user->email,
            'token_hash' => hash('sha256', $token),
            'otp_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(60),
        ]);

        $this->from(route('password.reset', [
            'token' => $token,
            'email' => $user->email,
        ]))->post(route('password.store'), [
            'token' => $token,
            'email' => $user->email,
            'otp_code' => '999999',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])
            ->assertSessionHasErrors('otp_code');
    }
}
