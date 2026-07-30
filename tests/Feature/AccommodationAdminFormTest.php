<?php

namespace Tests\Feature;

use App\Models\Accommodation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccommodationAdminFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_accommodation_with_installment_disabled(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)
            ->post(route('admin.accommodations.store'), $this->basePayload())
            ->assertRedirect(route('admin.accommodations.index'));

        $accommodation = Accommodation::query()->where('slug', 'ubud-retreat-villa')->firstOrFail();
        $this->assertFalse($accommodation->installment_enabled);
        $this->assertNull($accommodation->installment_count_mode);
        $this->assertNull($accommodation->installment_fixed_count);
    }

    public function test_admin_can_create_accommodation_with_flexible_installment(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)
            ->post(route('admin.accommodations.store'), array_merge($this->basePayload(), [
                'installment_enabled' => '1',
                'installment_count_mode' => 'flex',
            ]))
            ->assertRedirect(route('admin.accommodations.index'));

        $accommodation = Accommodation::query()->where('slug', 'ubud-retreat-villa')->firstOrFail();
        $this->assertTrue($accommodation->installment_enabled);
        $this->assertSame('flex', $accommodation->installment_count_mode);
        $this->assertNull($accommodation->installment_fixed_count);
    }

    public function test_admin_can_create_accommodation_with_fixed_installment_count(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)
            ->post(route('admin.accommodations.store'), array_merge($this->basePayload(), [
                'installment_enabled' => '1',
                'installment_count_mode' => 'fixed',
                'installment_fixed_count' => '4',
            ]))
            ->assertRedirect(route('admin.accommodations.index'));

        $accommodation = Accommodation::query()->where('slug', 'ubud-retreat-villa')->firstOrFail();
        $this->assertTrue($accommodation->installment_enabled);
        $this->assertSame('fixed', $accommodation->installment_count_mode);
        $this->assertSame(4, $accommodation->installment_fixed_count);
    }

    public function test_fixed_mode_without_a_fixed_count_fails_validation(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)
            ->post(route('admin.accommodations.store'), array_merge($this->basePayload(), [
                'installment_enabled' => '1',
                'installment_count_mode' => 'fixed',
            ]))
            ->assertSessionHasErrors('installment_fixed_count');

        $this->assertDatabaseMissing('accommodations', ['slug' => 'ubud-retreat-villa']);
    }

    public function test_enabling_installment_without_a_mode_fails_validation(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)
            ->post(route('admin.accommodations.store'), array_merge($this->basePayload(), [
                'installment_enabled' => '1',
            ]))
            ->assertSessionHasErrors('installment_count_mode');
    }

    public function test_admin_disabling_installment_on_update_clears_mode_and_fixed_count(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $accommodation = Accommodation::factory()->create([
            'installment_enabled' => true,
            'installment_count_mode' => 'fixed',
            'installment_fixed_count' => 3,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.accommodations.update', $accommodation), array_merge(
                $this->basePayload(),
                [
                    'slug' => $accommodation->slug,
                    'installment_enabled' => '0',
                ],
            ))
            ->assertRedirect(route('admin.accommodations.index'));

        $accommodation->refresh();
        $this->assertFalse($accommodation->installment_enabled);
        $this->assertNull($accommodation->installment_count_mode);
        $this->assertNull($accommodation->installment_fixed_count);
    }

    /**
     * @return array<string, mixed>
     */
    private function basePayload(): array
    {
        return [
            'title' => 'Ubud Retreat Villa',
            'slug' => 'ubud-retreat-villa',
            'description' => 'A quiet retreat in Ubud.',
            'currency_code' => 'USD',
            'is_active' => '1',
            'installment_enabled' => '0',
        ];
    }
}
