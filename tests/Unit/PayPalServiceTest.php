<?php

namespace Tests\Unit;

use App\Services\PayPalService;
use Tests\TestCase;

class PayPalServiceTest extends TestCase
{
    public function test_it_marks_sandbox_environment_from_paypal_base_url(): void
    {
        config()->set('services.paypal.base_url', 'https://api-m.sandbox.paypal.com');

        $this->assertSame('sandbox', app(PayPalService::class)->environment());
    }

    public function test_it_marks_live_environment_from_paypal_base_url(): void
    {
        config()->set('services.paypal.base_url', 'https://api-m.paypal.com');

        $this->assertSame('live', app(PayPalService::class)->environment());
    }
}
