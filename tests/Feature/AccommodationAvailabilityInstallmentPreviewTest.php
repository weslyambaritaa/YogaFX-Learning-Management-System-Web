<?php

namespace Tests\Feature;

use App\Models\Accommodation;
use App\Models\AccommodationBooking;
use App\Models\AccommodationRoomType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccommodationAvailabilityInstallmentPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_installment_preview_is_null_when_accommodation_has_installment_disabled(): void
    {
        $roomType = $this->createRoomType(['installment_enabled' => false]);

        $response = $this->postJson(
            route('stay.availability', $roomType->accommodation),
            $this->payload($roomType, checkInDays: 90),
        );

        $response->assertOk();
        $this->assertNull($response->json('installment'));
    }

    public function test_installment_preview_is_populated_when_enabled_and_eligible(): void
    {
        $roomType = $this->createRoomType([
            'installment_enabled' => true,
            'installment_count_mode' => 'flex',
        ]);

        $response = $this->postJson(
            route('stay.availability', $roomType->accommodation),
            $this->payload($roomType, checkInDays: 90),
        );

        $response->assertOk();
        $installment = $response->json('installment');

        $this->assertNotNull($installment);
        $this->assertSame(2, $installment['minimum_installment_count']);
        $this->assertGreaterThanOrEqual(2, $installment['maximum_installment_count']);
        $this->assertSame('flex', $installment['count_mode']);
        $this->assertNotEmpty($installment['schedule_breakdown']);
    }

    public function test_installment_preview_is_null_when_not_enough_billing_dates_fit(): void
    {
        $roomType = $this->createRoomType([
            'installment_enabled' => true,
            'installment_count_mode' => 'flex',
        ]);

        $response = $this->postJson(
            route('stay.availability', $roomType->accommodation),
            $this->payload($roomType, checkInDays: 10),
        );

        $response->assertOk();
        $this->assertNull($response->json('installment'));
    }

    public function test_installment_preview_is_null_when_room_is_unavailable(): void
    {
        $roomType = $this->createRoomType([
            'installment_enabled' => true,
            'installment_count_mode' => 'flex',
        ], totalRooms: 1);

        AccommodationBooking::factory()
            ->confirmed()
            ->create([
                'accommodation_id' => $roomType->accommodation_id,
                'accommodation_room_type_id' => $roomType->id,
                'check_in_date' => now()->addDays(90)->toDateString(),
                'check_out_date' => now()->addDays(92)->toDateString(),
            ]);

        $response = $this->postJson(
            route('stay.availability', $roomType->accommodation),
            $this->payload($roomType, checkInDays: 90),
        );

        $response->assertOk();
        $this->assertFalse($response->json('available'));
        $this->assertNull($response->json('installment'));
    }

    public function test_installment_preview_reflects_fixed_mode_clamping(): void
    {
        $roomType = $this->createRoomType([
            'installment_enabled' => true,
            'installment_count_mode' => 'fixed',
            'installment_fixed_count' => 10,
        ]);

        // ~2 months out only fits 3 installments — fixed count of 10 clamps down.
        $response = $this->postJson(
            route('stay.availability', $roomType->accommodation),
            $this->payload($roomType, checkInDays: 60),
        );

        $response->assertOk();
        $installment = $response->json('installment');

        $this->assertNotNull($installment);
        $this->assertSame('fixed', $installment['count_mode']);
        $this->assertSame($installment['maximum_installment_count'], $installment['installment_count']);
        $this->assertLessThan(10, $installment['installment_count']);
    }

    public function test_installment_preview_honors_selected_installment_count_in_flex_mode(): void
    {
        $roomType = $this->createRoomType([
            'installment_enabled' => true,
            'installment_count_mode' => 'flex',
        ]);

        $response = $this->postJson(
            route('stay.availability', $roomType->accommodation),
            array_merge($this->payload($roomType, checkInDays: 150), ['installment_count' => 3]),
        );

        $response->assertOk();
        $installment = $response->json('installment');

        $this->assertNotNull($installment);
        $this->assertSame(3, $installment['installment_count']);
        $this->assertCount(3, $installment['schedule_breakdown']);
    }

    public function test_installment_preview_falls_back_to_default_when_selected_count_is_stale(): void
    {
        $roomType = $this->createRoomType([
            'installment_enabled' => true,
            'installment_count_mode' => 'flex',
        ]);

        // 5 installments requested, but only ~90 days out only fits 3-4 —
        // simulate a stale selection left over from a longer date range.
        $response = $this->postJson(
            route('stay.availability', $roomType->accommodation),
            array_merge($this->payload($roomType, checkInDays: 90), ['installment_count' => 15]),
        );

        $response->assertOk();
        $installment = $response->json('installment');

        // Falls back to the default (maximum) plan instead of erroring.
        $this->assertNotNull($installment);
        $this->assertSame($installment['maximum_installment_count'], $installment['installment_count']);
    }

    /**
     * @param  array<string, mixed>  $accommodationOverrides
     */
    private function createRoomType(array $accommodationOverrides = [], int $totalRooms = 1): AccommodationRoomType
    {
        $accommodation = Accommodation::factory()->create(array_merge([
            'is_active' => true,
            'currency_code' => 'USD',
        ], $accommodationOverrides));

        return AccommodationRoomType::factory()->create([
            'accommodation_id' => $accommodation->id,
            'is_active' => true,
            'total_rooms' => $totalRooms,
            'price' => 100,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(AccommodationRoomType $roomType, int $checkInDays): array
    {
        return [
            'room_type_id' => $roomType->id,
            'check_in_date' => now()->addDays($checkInDays)->toDateString(),
            'check_out_date' => now()->addDays($checkInDays + 2)->toDateString(),
        ];
    }
}
