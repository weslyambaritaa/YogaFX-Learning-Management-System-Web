<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PasswordConfirmationTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirm_password_routes_are_not_available_in_phase_one_auth_foundation(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/confirm-password')->assertNotFound();
        $this->actingAs($user)->post('/confirm-password', [])->assertNotFound();
    }
}
