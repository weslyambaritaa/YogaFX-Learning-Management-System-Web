<?php

namespace Tests\Feature;

use App\Models\AccessTier;
use App\Models\PendingRegistration;
use App\Services\PayPalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicPaymentLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_payment_link_route_locks_scoreboard_to_matching_tier(): void
    {
        $starterTier = AccessTier::factory()->create([
            'name' => 'Starter Kit',
            'slug' => AccessTier::SLUG_STARTER_KIT,
            'payment_link' => '/starter-kit',
            'is_active' => true,
        ]);

        AccessTier::factory()->create([
            'name' => 'Online',
            'slug' => AccessTier::SLUG_ONLINE,
            'payment_link' => '/online',
            'is_active' => true,
        ]);

        $response = $this->get('/starter-kit');

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Public/Scoreboard')
            ->where('selected_access_tier_id', $starterTier->id)
            ->where('is_access_tier_locked', true)
            ->where('submit_url', url('/starter-kit'))
            ->has('accessTiers', 1)
            ->where('accessTiers.0.slug', AccessTier::SLUG_STARTER_KIT)
            ->where('accessTiers.0.payment_link', '/starter-kit'));
    }

    public function test_starterkit_alias_route_locks_scoreboard_to_matching_tier(): void
    {
        $starterTier = AccessTier::factory()->create([
            'name' => 'Starter Kit',
            'slug' => AccessTier::SLUG_STARTER_KIT,
            'payment_link' => '/starter-kit',
            'is_active' => true,
        ]);

        $this->get('/starterkit')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Public/Scoreboard')
                ->where('selected_access_tier_id', $starterTier->id)
                ->where('is_access_tier_locked', true)
                ->where('submit_url', url('/starterkit')));
    }

    public function test_product_payment_link_submission_creates_pending_registration_for_expected_tier_only(): void
    {
        $starterTier = AccessTier::factory()->create([
            'name' => 'Starter Kit',
            'slug' => AccessTier::SLUG_STARTER_KIT,
            'payment_link' => '/starter-kit',
            'price' => 150,
            'is_active' => true,
        ]);

        $onlineTier = AccessTier::factory()->create([
            'name' => 'Online',
            'slug' => AccessTier::SLUG_ONLINE,
            'payment_link' => '/online',
            'price' => 250,
            'is_active' => true,
        ]);

        $validPayload = [
            'first_name' => 'Ava',
            'last_name' => 'Stone',
            'email' => 'ava@example.com',
            'phone_country_code' => '+62',
            'phone_number' => '81234567890',
            'country' => 'Indonesia',
            'access_tier_id' => $starterTier->id,
        ];

        $this->post('/starter-kit', $validPayload)
            ->assertRedirect();

        $this->assertDatabaseHas('pending_registrations', [
            'access_tier_id' => $starterTier->id,
            'email' => 'ava@example.com',
        ]);

        $this->post('/starter-kit', [
            'first_name' => 'Nina',
            'last_name' => 'Hart',
            'email' => 'nina@example.com',
            'phone_country_code' => '+62',
            'phone_number' => '81234567891',
            'country' => 'Indonesia',
            'access_tier_id' => $onlineTier->id,
        ])->assertNotFound();

        $this->assertDatabaseMissing('pending_registrations', [
            'access_tier_id' => $onlineTier->id,
            'email' => 'nina@example.com',
        ]);
    }

    public function test_legacy_scoreboard_checkout_link_still_uses_signed_checkout_route(): void
    {
        $onlineTier = AccessTier::factory()->create([
            'name' => 'Online',
            'slug' => AccessTier::SLUG_ONLINE,
            'payment_link' => '/online',
            'price' => 299,
        ]);

        $pendingRegistration = PendingRegistration::query()->create([
            'access_tier_id' => $onlineTier->id,
            'first_name' => 'Lina',
            'last_name' => 'West',
            'email' => 'lina@example.com',
            'phone' => '+6281234567000',
            'country' => 'Indonesia',
            'amount_snapshot' => 299,
            'status' => PendingRegistration::STATUS_CREATED,
        ]);

        $response = $this->get(route('lead-registration.submitted', $pendingRegistration));

        $response
            ->assertRedirect()
            ->assertRedirectToSignedRoute('checkout.show', [
                'pendingRegistration' => $pendingRegistration->id,
                'accessTierSlug' => AccessTier::SLUG_ONLINE,
            ]);
    }

    public function test_public_scoreboard_submission_can_return_embedded_checkout_payload(): void
    {
        $onlineTier = AccessTier::factory()->create([
            'name' => 'Online',
            'slug' => AccessTier::SLUG_ONLINE,
            'payment_link' => '/online',
            'price' => 299,
            'is_active' => true,
        ]);

        $this->mock(PayPalService::class, function ($mock): void {
            $mock->shouldReceive('generateClientToken')
                ->once()
                ->andReturn('PAYPAL-CLIENT-TOKEN-INLINE-001');
            $mock->shouldReceive('clientId')
                ->once()
                ->andReturn('PAYPAL-CLIENT-ID-INLINE-001');
        });

        $response = $this->postJson('/online', [
            'first_name' => 'Lina',
            'last_name' => 'West',
            'email' => 'lina@example.com',
            'phone_country_code' => '+62',
            'phone_number' => '81234567000',
            'country' => 'Indonesia',
            'access_tier_id' => $onlineTier->id,
        ]);

        $pendingRegistration = PendingRegistration::query()->firstOrFail();

        $response
            ->assertOk()
            ->assertJsonPath('status', 'checkout_ready')
            ->assertJsonPath('checkout.access_tier.slug', AccessTier::SLUG_ONLINE)
            ->assertJsonPath('checkout.paypal.client_id', 'PAYPAL-CLIENT-ID-INLINE-001')
            ->assertJsonPath('checkout.paypal.client_token', 'PAYPAL-CLIENT-TOKEN-INLINE-001')
            ->assertJsonPath(
                'checkout.create_order_url',
                URL::temporarySignedRoute('checkout.orders.store', now()->addDays(7), [
                    'pendingRegistration' => $pendingRegistration->id,
                    'accessTierSlug' => AccessTier::SLUG_ONLINE,
                ]),
            );

        $this->assertDatabaseHas('pending_registrations', [
            'id' => $pendingRegistration->id,
            'status' => PendingRegistration::STATUS_CHECKOUT_OPENED,
        ]);
    }
}
