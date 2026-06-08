<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_verification_routes_are_not_available_in_phase_one_auth_foundation(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get('/verify-email')->assertNotFound();
        $this->actingAs($user)->post('/email/verification-notification')->assertNotFound();
    }
}
