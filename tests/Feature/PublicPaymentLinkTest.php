<?php

namespace Tests\Feature;

use App\Models\AccessTier;
use App\Models\Package;
use App\Models\PendingRegistration;
use App\Services\PayPalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
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
            'is_active' => true,
        ]);

        $starterPackage = Package::factory()->create([
            'access_tier_id' => $starterTier->id,
            'title' => 'Starter-kit Standard',
            'slug' => 'starter-kit-standard',
            'price' => 150,
            'currency_code' => AccessTier::CURRENCY_IDR,
        ]);

        $onlineTier = AccessTier::factory()->create([
            'name' => 'Online',
            'slug' => AccessTier::SLUG_ONLINE,
            'is_active' => true,
        ]);
        Package::factory()->create([
            'access_tier_id' => $onlineTier->id,
            'title' => 'Online Standard',
            'slug' => 'online-standard',
        ]);

        $response = $this->get('/starter-kit');

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Public/Scoreboard')
            ->where('selected_package_id', $starterPackage->id)
            ->where('is_package_locked', true)
            ->where('submit_url', url('/starter-kit'))
            ->has('packages', 1)
            ->where('packages.0.slug', 'starter-kit-standard')
            ->where('packages.0.access_tier.slug', AccessTier::SLUG_STARTER_KIT));
    }

    public function test_starterkit_alias_route_locks_scoreboard_to_matching_tier(): void
    {
        $starterTier = AccessTier::factory()->create([
            'name' => 'Starter Kit',
            'slug' => AccessTier::SLUG_STARTER_KIT,
            'is_active' => true,
        ]);
        $starterPackage = Package::factory()->create([
            'access_tier_id' => $starterTier->id,
            'title' => 'Starter-kit Standard',
            'slug' => 'starter-kit-standard',
        ]);

        $this->get('/starterkit')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Public/Scoreboard')
                ->where('selected_package_id', $starterPackage->id)
                ->where('is_package_locked', true)
                ->where('submit_url', url('/starterkit')));
    }

    public function test_product_payment_link_submission_creates_pending_registration_for_resolved_package(): void
    {
        $starterTier = AccessTier::factory()->create([
            'name' => 'Starter Kit',
            'slug' => AccessTier::SLUG_STARTER_KIT,
            'is_active' => true,
        ]);
        $starterPackage = Package::factory()->create([
            'access_tier_id' => $starterTier->id,
            'title' => 'Starter-kit Standard',
            'slug' => 'starter-kit-standard',
            'price' => 150,
            'currency_code' => AccessTier::CURRENCY_GBP,
        ]);

        $onlineTier = AccessTier::factory()->create([
            'name' => 'Online',
            'slug' => AccessTier::SLUG_ONLINE,
            'is_active' => true,
        ]);
        $onlinePackage = Package::factory()->create([
            'access_tier_id' => $onlineTier->id,
            'title' => 'Online Standard',
            'slug' => 'online-standard',
            'price' => 250,
            'currency_code' => AccessTier::CURRENCY_USD,
        ]);

        $validPayload = [
            'first_name' => 'Ava',
            'last_name' => 'Stone',
            'email' => 'ava@example.com',
            'phone_country_code' => '+62',
            'phone_number' => '81234567890',
            'country' => 'Indonesia',
            'package_id' => $starterPackage->id,
        ];

        $this->post('/starter-kit', $validPayload)
            ->assertRedirect();

        $this->assertDatabaseHas('pending_registrations', [
            'access_tier_id' => $starterTier->id,
            'package_id' => $starterPackage->id,
            'currency_code' => AccessTier::CURRENCY_GBP,
            'email' => 'ava@example.com',
        ]);

        $this->post('/starter-kit', [
            'first_name' => 'Nina',
            'last_name' => 'Hart',
            'email' => 'nina@example.com',
            'phone_country_code' => '+62',
            'phone_number' => '81234567891',
            'country' => 'Indonesia',
            'package_id' => $onlinePackage->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('pending_registrations', [
            'access_tier_id' => $starterTier->id,
            'package_id' => $starterPackage->id,
            'email' => 'nina@example.com',
        ]);
    }

    public function test_legacy_scoreboard_checkout_link_still_uses_signed_checkout_route(): void
    {
        $onlineTier = AccessTier::factory()->create([
            'name' => 'Online',
            'slug' => AccessTier::SLUG_ONLINE,
            'price' => 299,
        ]);
        $onlinePackage = Package::factory()->create([
            'access_tier_id' => $onlineTier->id,
            'title' => 'Online Standard',
            'slug' => 'online-standard',
            'price' => 299,
            'currency_code' => AccessTier::CURRENCY_GBP,
        ]);

        $pendingRegistration = PendingRegistration::query()->create([
            'access_tier_id' => $onlineTier->id,
            'package_id' => $onlinePackage->id,
            'first_name' => 'Lina',
            'last_name' => 'West',
            'email' => 'lina@example.com',
            'phone' => '+6281234567000',
            'country' => 'Indonesia',
            'amount_snapshot' => 299,
            'currency_code' => AccessTier::CURRENCY_GBP,
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
            'is_active' => true,
        ]);
        $onlinePackage = Package::factory()->create([
            'access_tier_id' => $onlineTier->id,
            'title' => 'Online Standard',
            'slug' => 'online-standard',
            'price' => 299,
            'currency_code' => AccessTier::CURRENCY_GBP,
            'is_active' => true,
        ]);

        $this->mock(PayPalService::class, function ($mock): void {
            $mock->shouldReceive('clientId')
                ->once()
                ->andReturn('PAYPAL-CLIENT-ID-INLINE-001');
            $mock->shouldReceive('environment')
                ->once()
                ->andReturn('sandbox');
        });

        $response = $this->postJson('/online', [
            'first_name' => 'Lina',
            'last_name' => 'West',
            'email' => 'lina@example.com',
            'phone_country_code' => '+62',
            'phone_number' => '81234567000',
            'country' => 'Indonesia',
            'package_id' => $onlinePackage->id,
        ]);

        $pendingRegistration = PendingRegistration::query()->firstOrFail();

        $response
            ->assertOk()
            ->assertJsonPath('status', 'checkout_ready')
            ->assertJsonPath('checkout.access_tier.slug', AccessTier::SLUG_ONLINE)
            ->assertJsonPath('checkout.package.slug', 'online-standard')
            ->assertJsonPath('checkout.paypal.client_id', 'PAYPAL-CLIENT-ID-INLINE-001')
            ->assertJsonPath('checkout.paypal.client_token', null)
            ->assertJsonPath(
                'checkout.create_order_url',
                URL::temporarySignedRoute('checkout.orders.store', now()->addDays(7), [
                    'pendingRegistration' => $pendingRegistration->id,
                    'accessTierSlug' => AccessTier::SLUG_ONLINE,
                ]),
            );

        $this->assertDatabaseHas('pending_registrations', [
            'id' => $pendingRegistration->id,
            'package_id' => $onlinePackage->id,
            'status' => PendingRegistration::STATUS_CHECKOUT_OPENED,
        ]);
    }

    public function test_direct_package_link_requires_active_assigned_package(): void
    {
        $tier = AccessTier::factory()->create([
            'slug' => AccessTier::SLUG_MASTER_CLASS,
        ]);

        $package = Package::factory()->create([
            'access_tier_id' => $tier->id,
            'title' => 'Masterclass Easter',
            'slug' => 'masterclass-easter',
            'is_active' => true,
        ]);

        $this->get('/p/masterclass-easter')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Public/Scoreboard')
                ->where('selected_package_id', $package->id)
                ->where('is_package_locked', true));

        $package->update(['access_tier_id' => null]);

        $this->get('/p/masterclass-easter')->assertNotFound();
    }

    public function test_direct_package_link_renders_server_side_open_graph_metadata_in_initial_html(): void
    {
        config()->set('app.url', 'http://127.0.0.1:8000');
        config()->set('app.public_url', 'http://192.168.0.11:8000');

        Storage::disk('local')->put('packages/images/masterclass-share.jpg', 'fake-package-image');

        $tier = AccessTier::factory()->create([
            'name' => 'Masterclass',
            'slug' => AccessTier::SLUG_MASTER_CLASS,
            'is_active' => true,
        ]);

        $package = Package::factory()->create([
            'access_tier_id' => $tier->id,
            'title' => 'Masterclass Standard',
            'slug' => 'masterclass-standard',
            'description' => 'Deepen your yoga journey with guided masterclass content.',
            'image' => 'packages/images/masterclass-share.jpg',
            'is_active' => true,
        ]);

        $response = $this->get('/p/masterclass-standard');

        $response->assertOk();
        $response->assertSee('<title inertia>Masterclass Standard | YogaFX</title>', false);
        $response->assertSee('meta property="og:title" content="Masterclass Standard | YogaFX"', false);
        $response->assertSee('meta property="og:description" content="Deepen your yoga journey with guided masterclass content."', false);
        $response->assertSee('meta property="og:url" content="http://192.168.0.11:8000/p/masterclass-standard"', false);
        $response->assertSee('meta property="og:image" content="http://192.168.0.11:8000/public-media/package/'.$package->id.'/image', false);
        $response->assertSee('meta name="twitter:image" content="http://192.168.0.11:8000/public-media/package/'.$package->id.'/image', false);
    }

    public function test_public_package_image_route_is_accessible_without_authentication(): void
    {
        Storage::disk('local')->put('packages/images/masterclass-share.jpg', 'fake-package-image');

        $tier = AccessTier::factory()->create([
            'slug' => AccessTier::SLUG_MASTER_CLASS,
            'is_active' => true,
        ]);

        $package = Package::factory()->create([
            'access_tier_id' => $tier->id,
            'slug' => 'masterclass-standard',
            'image' => 'packages/images/masterclass-share.jpg',
            'is_active' => true,
        ]);

        $this->get(route('public-media.show', [
            'entity' => 'package',
            'id' => $package->id,
            'field' => 'image',
        ]))->assertOk();
    }

    public function test_direct_package_link_submission_returns_checkout_payload_for_locked_package(): void
    {
        $tier = AccessTier::factory()->create([
            'name' => 'Masterclass',
            'slug' => AccessTier::SLUG_MASTER_CLASS,
            'is_active' => true,
        ]);
        $package = Package::factory()->create([
            'access_tier_id' => $tier->id,
            'title' => 'Masterclass Standard',
            'slug' => 'masterclass-standard',
            'price' => 300,
            'currency_code' => AccessTier::CURRENCY_USD,
            'is_active' => true,
        ]);

        $this->mock(PayPalService::class, function ($mock): void {
            $mock->shouldReceive('clientId')
                ->once()
                ->andReturn('PAYPAL-CLIENT-ID-DIRECT-001');
            $mock->shouldReceive('environment')
                ->once()
                ->andReturn('sandbox');
        });

        $response = $this->postJson('/p/masterclass-standard', [
            'first_name' => 'Ayla',
            'last_name' => 'River',
            'email' => 'ayla@example.com',
            'phone_country_code' => '+62',
            'phone_number' => '81234567890',
            'country' => 'Indonesia',
            'package_id' => 999999,
        ]);

        $pendingRegistration = PendingRegistration::query()->firstOrFail();

        $response
            ->assertOk()
            ->assertJsonPath('status', 'checkout_ready')
            ->assertJsonPath('checkout.package.id', $package->id)
            ->assertJsonPath('checkout.package.slug', 'masterclass-standard')
            ->assertJsonPath('checkout.amount', 300)
            ->assertJsonPath('checkout.currency_code', AccessTier::CURRENCY_USD)
            ->assertJsonPath('checkout.paypal.client_id', 'PAYPAL-CLIENT-ID-DIRECT-001')
            ->assertJsonPath('checkout.paypal.client_token', null)
            ->assertJsonPath(
                'checkout.create_order_url',
                URL::temporarySignedRoute('checkout.orders.store', now()->addDays(7), [
                    'pendingRegistration' => $pendingRegistration->id,
                    'accessTierSlug' => AccessTier::SLUG_MASTER_CLASS,
                ]),
            );

        $this->assertDatabaseHas('pending_registrations', [
            'id' => $pendingRegistration->id,
            'package_id' => $package->id,
            'access_tier_id' => $tier->id,
            'amount_snapshot' => '300.00',
            'currency_code' => AccessTier::CURRENCY_USD,
            'status' => PendingRegistration::STATUS_CHECKOUT_OPENED,
        ]);
    }

    public function test_direct_package_link_submission_rejects_inactive_package_with_clear_error(): void
    {
        $tier = AccessTier::factory()->create([
            'slug' => AccessTier::SLUG_MASTER_CLASS,
            'is_active' => true,
        ]);
        $package = Package::factory()->create([
            'access_tier_id' => $tier->id,
            'slug' => 'masterclass-standard',
            'is_active' => false,
        ]);

        $this->postJson('/p/masterclass-standard', [
            'first_name' => 'Ayla',
            'last_name' => 'River',
            'email' => 'ayla@example.com',
            'phone_country_code' => '+62',
            'phone_number' => '81234567890',
            'country' => 'Indonesia',
            'package_id' => $package->id,
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.package_id.0', 'This package is currently unavailable for checkout.');
    }

    public function test_direct_package_link_submission_rejects_unassigned_package_with_clear_error(): void
    {
        $tier = AccessTier::factory()->create([
            'slug' => AccessTier::SLUG_MASTER_CLASS,
            'is_active' => true,
        ]);
        $package = Package::factory()->create([
            'access_tier_id' => $tier->id,
            'slug' => 'masterclass-standard',
            'is_active' => true,
        ]);

        $package->update(['access_tier_id' => null]);

        $this->postJson('/p/masterclass-standard', [
            'first_name' => 'Ayla',
            'last_name' => 'River',
            'email' => 'ayla@example.com',
            'phone_country_code' => '+62',
            'phone_number' => '81234567890',
            'country' => 'Indonesia',
            'package_id' => $package->id,
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.package_id.0', 'This package is currently unavailable for checkout.');
    }

    public function test_masterclass_legacy_route_resolves_active_assigned_package(): void
    {
        $tier = AccessTier::factory()->create([
            'name' => 'Masterclass',
            'slug' => AccessTier::SLUG_MASTER_CLASS,
            'is_active' => true,
        ]);
        $package = Package::factory()->create([
            'access_tier_id' => $tier->id,
            'title' => 'Masterclass Standard',
            'slug' => 'masterclass-standard',
            'is_active' => true,
        ]);

        $this->get('/masterclass')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Public/Scoreboard')
                ->where('selected_package_id', $package->id)
                ->where('is_package_locked', true)
                ->where('submit_url', url('/masterclass'))
                ->where('packages.0.slug', 'masterclass-standard'));
    }
}
