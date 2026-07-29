<?php

namespace Tests\Feature;

use App\Models\Accommodation;
use App\Models\AccommodationBooking;
use App\Models\AccommodationRoomType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AccommodationBookingCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.paypal.base_url', 'https://api-m.sandbox.paypal.com');
        config()->set('services.paypal.client_id', 'client-id');
        config()->set('services.paypal.secret', 'client-secret');
    }

    public function test_create_order_creates_pending_booking_with_hold_and_paypal_order(): void
    {
        $roomType = $this->createBookableRoomType();
        $accommodation = $roomType->accommodation;
        $this->fakePayPalOrderCreation('ORDER-001');

        $response = $this->postJson(
            route('stay.orders.store', $accommodation),
            $this->validOrderPayload($roomType),
        );

        $response->assertOk();
        $response->assertJsonPath('status', 'created');
        $response->assertJsonPath('order_id', 'ORDER-001');
        $this->assertNotEmpty($response->json('capture_url'));
        $this->assertNotEmpty($response->json('cancel_url'));

        $booking = AccommodationBooking::query()->findOrFail($response->json('booking_id'));
        $this->assertSame(AccommodationBooking::STATUS_PENDING_PAYMENT, $booking->status);
        $this->assertSame('ORDER-001', $booking->paypal_order_id);
        $this->assertNotNull($booking->hold_expires_at);
        $this->assertMatchesRegularExpression('/^BOOK-\d{4}-\d{6}$/', $booking->booking_number);
        $this->assertSame('200.00', $booking->total_amount);
    }

    public function test_create_order_returns_409_when_room_is_fully_booked(): void
    {
        $roomType = $this->createBookableRoomType(totalRooms: 1);
        $accommodation = $roomType->accommodation;

        AccommodationBooking::factory()
            ->confirmed()
            ->create([
                'accommodation_id' => $accommodation->id,
                'accommodation_room_type_id' => $roomType->id,
                'check_in_date' => now()->addDays(5)->toDateString(),
                'check_out_date' => now()->addDays(7)->toDateString(),
            ]);

        $response = $this->postJson(
            route('stay.orders.store', $accommodation),
            $this->validOrderPayload($roomType),
        );

        $response->assertStatus(409);
        $response->assertJsonPath('status', 'unavailable');

        Http::assertNothingSent();
    }

    public function test_capture_order_confirms_booking_when_paypal_completes(): void
    {
        $roomType = $this->createBookableRoomType();
        $accommodation = $roomType->accommodation;
        $this->fakePayPalOrderCreation('ORDER-002');

        $createResponse = $this->postJson(
            route('stay.orders.store', $accommodation),
            $this->validOrderPayload($roomType),
        );

        $captureUrl = $createResponse->json('capture_url');
        $bookingId = $createResponse->json('booking_id');

        $this->fakePayPalCapture('ORDER-002', 'COMPLETED');

        $captureResponse = $this->postJson($captureUrl, ['order_id' => 'ORDER-002']);

        $captureResponse->assertOk();
        $captureResponse->assertJsonPath('status', 'success');
        $this->assertNotEmpty($captureResponse->json('redirect_url'));

        $booking = AccommodationBooking::query()->findOrFail($bookingId);
        $this->assertSame(AccommodationBooking::STATUS_CONFIRMED, $booking->status);
        $this->assertNotNull($booking->paid_at);

        // The signed redirect_url returned above should now render the success page.
        $this->get($captureResponse->json('redirect_url'))->assertOk();

        $this->assertDatabaseHas('email_logs', [
            'notification_type' => 'accommodation_booking_confirmed',
            'reference_type' => 'accommodation_booking',
            'reference_id' => $booking->id,
            'recipient_type' => 'user',
            'recipient_email' => $booking->guest_email,
            'status' => 'sent',
        ]);
    }

    public function test_capture_order_does_not_send_confirmation_email_when_oversold(): void
    {
        $roomType = $this->createBookableRoomType(totalRooms: 1);
        $accommodation = $roomType->accommodation;
        $this->fakePayPalOrderCreation('ORDER-006');

        $createResponse = $this->postJson(
            route('stay.orders.store', $accommodation),
            $this->validOrderPayload($roomType),
        );

        $captureUrl = $createResponse->json('capture_url');
        $bookingId = $createResponse->json('booking_id');

        AccommodationBooking::factory()
            ->confirmed()
            ->create([
                'accommodation_id' => $accommodation->id,
                'accommodation_room_type_id' => $roomType->id,
                'check_in_date' => now()->addDays(5)->toDateString(),
                'check_out_date' => now()->addDays(7)->toDateString(),
            ]);

        $this->fakePayPalCapture('ORDER-006', 'COMPLETED');

        $this->postJson($captureUrl, ['order_id' => 'ORDER-006']);

        $this->assertDatabaseMissing('email_logs', [
            'notification_type' => 'accommodation_booking_confirmed',
            'reference_id' => $bookingId,
        ]);
    }

    public function test_capture_order_does_not_confirm_when_room_became_unavailable_in_the_meantime(): void
    {
        $roomType = $this->createBookableRoomType(totalRooms: 1);
        $accommodation = $roomType->accommodation;
        $this->fakePayPalOrderCreation('ORDER-003');

        $createResponse = $this->postJson(
            route('stay.orders.store', $accommodation),
            $this->validOrderPayload($roomType),
        );

        $captureUrl = $createResponse->json('capture_url');
        $bookingId = $createResponse->json('booking_id');

        // Simulate another booking confirming the only room while this one
        // was still waiting on PayPal approval.
        AccommodationBooking::factory()
            ->confirmed()
            ->create([
                'accommodation_id' => $accommodation->id,
                'accommodation_room_type_id' => $roomType->id,
                'check_in_date' => now()->addDays(5)->toDateString(),
                'check_out_date' => now()->addDays(7)->toDateString(),
            ]);

        $this->fakePayPalCapture('ORDER-003', 'COMPLETED');

        $captureResponse = $this->postJson($captureUrl, ['order_id' => 'ORDER-003']);

        $captureResponse->assertStatus(409);
        $captureResponse->assertJsonPath('status', 'failed');

        $booking = AccommodationBooking::query()->findOrFail($bookingId);
        $this->assertSame(AccommodationBooking::STATUS_PENDING_PAYMENT, $booking->status);
        $this->assertNull($booking->paid_at);
    }

    public function test_capture_order_marks_booking_expired_when_hold_has_passed(): void
    {
        $roomType = $this->createBookableRoomType();
        $accommodation = $roomType->accommodation;
        $this->fakePayPalOrderCreation('ORDER-004');

        $createResponse = $this->postJson(
            route('stay.orders.store', $accommodation),
            $this->validOrderPayload($roomType),
        );

        $captureUrl = $createResponse->json('capture_url');
        $bookingId = $createResponse->json('booking_id');

        $this->travelTo(now()->addMinutes(31));

        $captureResponse = $this->postJson($captureUrl, ['order_id' => 'ORDER-004']);

        $captureResponse->assertStatus(422);
        $captureResponse->assertJsonPath('status', 'failed');

        $booking = AccommodationBooking::query()->findOrFail($bookingId);
        $this->assertSame(AccommodationBooking::STATUS_EXPIRED, $booking->status);
    }

    public function test_cancel_order_marks_pending_booking_cancelled(): void
    {
        $roomType = $this->createBookableRoomType();
        $accommodation = $roomType->accommodation;
        $this->fakePayPalOrderCreation('ORDER-005');

        $createResponse = $this->postJson(
            route('stay.orders.store', $accommodation),
            $this->validOrderPayload($roomType),
        );

        $cancelUrl = $createResponse->json('cancel_url');
        $bookingId = $createResponse->json('booking_id');

        $cancelResponse = $this->postJson($cancelUrl);

        $cancelResponse->assertOk();
        $cancelResponse->assertJsonPath('status', 'cancelled');

        $booking = AccommodationBooking::query()->findOrFail($bookingId);
        $this->assertSame(AccommodationBooking::STATUS_CANCELLED, $booking->status);
    }

    public function test_success_page_returns_404_for_unconfirmed_booking(): void
    {
        $roomType = $this->createBookableRoomType();
        $accommodation = $roomType->accommodation;

        $booking = AccommodationBooking::factory()
            ->pendingPayment()
            ->create([
                'accommodation_id' => $accommodation->id,
                'accommodation_room_type_id' => $roomType->id,
            ]);

        $url = URL::temporarySignedRoute('stay.bookings.success', now()->addDays(7), [
            'accommodation' => $accommodation,
            'booking' => $booking->id,
        ]);

        $this->get($url)->assertNotFound();
    }

    private function fakePayPalOrderCreation(string $orderId): void
    {
        Http::fake([
            'https://api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response([
                'access_token' => 'paypal-access-token',
            ]),
            'https://api-m.sandbox.paypal.com/v2/checkout/orders' => Http::response([
                'id' => $orderId,
                'links' => [[
                    'rel' => 'approve',
                    'href' => "https://www.paypal.com/checkoutnow?token={$orderId}",
                ]],
            ]),
        ]);
    }

    private function fakePayPalCapture(string $orderId, string $status): void
    {
        Http::fake([
            'https://api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response([
                'access_token' => 'paypal-access-token',
            ]),
            "https://api-m.sandbox.paypal.com/v2/checkout/orders/{$orderId}/capture" => Http::response([
                'status' => $status,
            ]),
        ]);
    }

    private function createBookableRoomType(int $totalRooms = 1): AccommodationRoomType
    {
        $accommodation = Accommodation::factory()->create([
            'is_active' => true,
            'currency_code' => 'USD',
        ]);

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
    private function validOrderPayload(AccommodationRoomType $roomType): array
    {
        return [
            'room_type_id' => $roomType->id,
            'check_in_date' => now()->addDays(5)->toDateString(),
            'check_out_date' => now()->addDays(7)->toDateString(),
            'guest_name' => 'Jane Doe',
            'guest_email' => 'jane@example.com',
            'guest_phone' => '+62812345678',
        ];
    }
}
