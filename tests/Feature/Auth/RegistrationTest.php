<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_routes_are_not_available_in_phase_one_auth_foundation(): void
    {
        $response = $this->get('/register');

        $response->assertNotFound();
    }
}
