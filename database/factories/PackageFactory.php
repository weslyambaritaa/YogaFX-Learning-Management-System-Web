<?php

namespace Database\Factories;

use App\Models\AccessTier;
use App\Models\Package;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Package>
 */
class PackageFactory extends Factory
{
    protected $model = Package::class;

    public function definition(): array
    {
        $title = Str::title(fake()->unique()->words(3, true));

        return [
            'access_tier_id' => AccessTier::factory(),
            'title' => $title,
            'slug' => Str::slug($title),
            'description' => fake()->sentence(),
            'image' => null,
            'price' => fake()->randomFloat(2, 49, 999),
            'currency_code' => fake()->randomElement(AccessTier::CURRENCY_OPTIONS),
            'is_active' => true,
            'installment_enabled' => false,
            'billing_interval_unit' => null,
            'billing_interval_count' => null,
            'fixed_billing_day' => null,
            'allowed_billing_days' => null,
            'installment_deadline_month' => null,
            'installment_deadline_day' => null,
            'paypal_product_id' => null,
            'paypal_plan_id' => null,
            'metadata' => null,
        ];
    }

    public function unassigned(): static
    {
        return $this->state(fn () => [
            'access_tier_id' => null,
        ]);
    }
}
