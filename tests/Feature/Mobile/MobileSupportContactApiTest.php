<?php

namespace Tests\Feature\Mobile;

use App\Models\SupportSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileSupportContactApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_support_contact_endpoint_is_public_and_returns_safe_defaults(): void
    {
        $this->getJson('/api/mobile/v1/support-contact')
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Support contact fetched successfully',
                'data' => [
                    'whatsapp' => null,
                    'email' => null,
                ],
            ]);
    }

    public function test_mobile_support_contact_endpoint_returns_admin_managed_values(): void
    {
        SupportSetting::query()->create([
            'support_whatsapp' => '+62 812-3456-789',
            'support_email' => 'support@yogafx.com',
        ]);

        $this->getJson('/api/mobile/v1/support-contact')
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Support contact fetched successfully',
                'data' => [
                    'whatsapp' => '628123456789',
                    'email' => 'support@yogafx.com',
                ],
            ]);
    }
}
