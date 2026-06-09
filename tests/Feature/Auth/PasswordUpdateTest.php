<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PasswordUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_password_update_route_is_not_available_in_phase_one_auth_foundation(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put('/password', [])->assertNotFound();
    }
}
