<?php

namespace Tests\Feature;

use App\Models\AccessTier;
use App\Models\Package;
use App\Models\User;
use App\Services\PackageResolverService;
use App\Services\PackageAssignmentService;
use Database\Seeders\AccessTierSeeder;
use Database\Seeders\PackageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PackageDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_package_can_be_created_without_assigned_tier(): void
    {
        $package = Package::factory()->unassigned()->create([
            'title' => 'Masterclass Easter',
            'slug' => 'masterclass-easter',
        ]);

        $this->assertNull($package->access_tier_id);
        $this->assertNull($package->accessTier);
    }

    public function test_assigning_package_to_tier_auto_unassigns_previous_package(): void
    {
        $tier = AccessTier::factory()->create([
            'slug' => AccessTier::SLUG_MASTER_CLASS,
        ]);

        $standard = Package::factory()->create([
            'access_tier_id' => $tier->id,
            'title' => 'Masterclass Standard',
            'slug' => 'masterclass-standard',
        ]);

        $promo = Package::factory()->unassigned()->create([
            'title' => 'Masterclass Easter',
            'slug' => 'masterclass-easter',
        ]);

        app(PackageAssignmentService::class)->assignToTier($promo, $tier);

        $this->assertNull($standard->fresh()->access_tier_id);
        $this->assertSame($tier->id, $promo->fresh()->access_tier_id);
    }

    public function test_package_seeder_creates_standard_packages_idempotently(): void
    {
        $this->seed(AccessTierSeeder::class);
        $this->seed(PackageSeeder::class);
        $this->seed(PackageSeeder::class);

        $this->assertSame(3, Package::query()->count());
        $this->assertDatabaseHas('packages', [
            'slug' => 'masterclass-standard',
        ]);
        $this->assertDatabaseHas('packages', [
            'slug' => 'online-standard',
        ]);
        $this->assertDatabaseHas('packages', [
            'slug' => 'starter-kit-standard',
        ]);
    }

    public function test_admin_can_create_package_and_assign_it_to_tier(): void
    {
        $admin = User::factory()->admin()->create();
        $tier = AccessTier::factory()->create([
            'slug' => AccessTier::SLUG_ONLINE,
            'name' => 'Online',
        ]);

        $this->actingAs($admin)->post(route('admin.packages.store'), [
            'title' => 'Online Standard',
            'slug' => 'online-standard',
            'description' => 'Main online offer.',
            'price' => 299,
            'currency_code' => AccessTier::CURRENCY_GBP,
            'is_active' => true,
            'installment_enabled' => false,
            'billing_interval_unit' => '',
            'billing_interval_count' => '',
            'fixed_billing_day' => '',
            'allowed_billing_days' => [],
            'installment_deadline_month' => '',
            'installment_deadline_day' => '',
            'access_tier_id' => $tier->id,
        ])->assertRedirect(route('admin.packages.index'));

        $this->assertDatabaseHas('packages', [
            'slug' => 'online-standard',
            'access_tier_id' => $tier->id,
            'price' => 299,
            'currency_code' => AccessTier::CURRENCY_GBP,
        ]);
    }

    public function test_package_resolver_ignores_inactive_and_unassigned_packages(): void
    {
        $tier = AccessTier::factory()->create([
            'slug' => AccessTier::SLUG_MASTER_CLASS,
            'is_active' => true,
        ]);

        $inactiveAssigned = Package::factory()->create([
            'access_tier_id' => $tier->id,
            'slug' => 'masterclass-inactive',
            'is_active' => false,
        ]);

        $activeUnassigned = Package::factory()->unassigned()->create([
            'slug' => 'masterclass-unassigned',
            'is_active' => true,
        ]);

        $activeAssigned = Package::factory()->create([
            'access_tier_id' => $tier->id,
            'slug' => 'masterclass-active',
            'is_active' => true,
        ]);

        $resolver = app(PackageResolverService::class);

        $this->assertTrue($resolver->resolveActivePackageForTierSlug('masterclass')?->is($activeAssigned));
        $this->assertTrue($resolver->resolveActivePackageBySlug('masterclass-active')?->is($activeAssigned));
        $this->assertNull($resolver->resolveActivePackageBySlug('masterclass-inactive'));
        $this->assertNull($resolver->resolveActivePackageBySlug('masterclass-unassigned'));
        $this->assertFalse($inactiveAssigned->isCheckoutAvailable());
        $this->assertFalse($activeUnassigned->isCheckoutAvailable());
    }

    public function test_admin_package_update_reassigns_tier_and_unassigns_previous_package(): void
    {
        $admin = User::factory()->admin()->create();
        $tier = AccessTier::factory()->create([
            'slug' => AccessTier::SLUG_ONLINE,
        ]);
        $standard = Package::factory()->create([
            'access_tier_id' => $tier->id,
            'title' => 'Online Standard',
            'slug' => 'online-standard',
        ]);
        $promo = Package::factory()->unassigned()->create([
            'title' => 'Online Easter',
            'slug' => 'online-easter',
        ]);

        $this->actingAs($admin)->patch(route('admin.packages.update', $promo), [
            'title' => 'Online Easter',
            'slug' => 'online-easter',
            'description' => 'Promo package.',
            'price' => 249,
            'currency_code' => AccessTier::CURRENCY_GBP,
            'is_active' => true,
            'installment_enabled' => true,
            'billing_interval_unit' => 'MONTH',
            'billing_interval_count' => 1,
            'fixed_billing_day' => '',
            'allowed_billing_days' => [1, 15],
            'installment_deadline_month' => 1,
            'installment_deadline_day' => 15,
            'access_tier_id' => $tier->id,
        ])->assertRedirect(route('admin.packages.index'));

        $this->assertNull($standard->fresh()->access_tier_id);
        $this->assertSame($tier->id, $promo->fresh()->access_tier_id);
        $this->assertTrue((bool) $promo->fresh()->installment_enabled);
        $this->assertSame([1, 15], $promo->fresh()->allowed_billing_days);
    }

    public function test_non_admin_cannot_access_package_admin_routes(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($student)
            ->get(route('admin.packages.index'))
            ->assertForbidden();
    }
}
