<?php

namespace Tests\Feature;

use App\Models\Accommodation;
use App\Models\AccommodationBooking;
use App\Models\AccommodationPaymentSubscription;
use App\Models\AccommodationRoomType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AccommodationInstallmentCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.paypal.base_url', 'https://api-m.sandbox.paypal.com');
        config()->set('services.paypal.client_id', 'client-id');
        config()->set('services.paypal.secret', 'client-secret');
    }

    public function test_create_installment_subscription_creates_pending_booking_with_hold_and_paypal_plan(): void
    {
        $roomType = $this->createBookableRoomType();
        $accommodation = $roomType->accommodation;
        $accommodation->update(['installment_enabled' => true]);
        $this->fakePayPalPlanCreation('PROD-INS-001', 'PLAN-INS-001');

        $response = $this->postJson(
            route('stay.installments.store', $accommodation),
            $this->validInstallmentPayload($roomType, checkInDays: 90),
        );

        $response->assertOk();
        $response->assertJsonPath('status', 'created');
        $response->assertJsonPath('provider_plan_id', 'PLAN-INS-001');
        $this->assertNotEmpty($response->json('approve_url'));
        $this->assertNotEmpty($response->json('cancel_url'));

        $booking = AccommodationBooking::query()->findOrFail($response->json('booking_id'));
        $this->assertSame(AccommodationBooking::STATUS_PENDING_PAYMENT, $booking->status);
        $this->assertNotNull($booking->hold_expires_at);
        $this->assertTrue($booking->hold_expires_at->greaterThan(now()->addMinutes(59)));
        $this->assertTrue($booking->hold_expires_at->lessThanOrEqualTo(now()->addMinutes(60)));

        $subscription = AccommodationPaymentSubscription::query()
            ->where('accommodation_booking_id', $booking->id)
            ->firstOrFail();

        $this->assertSame(AccommodationPaymentSubscription::STATUS_DRAFT, $subscription->status);
        $this->assertSame('PROD-INS-001', $subscription->provider_product_id);
        $this->assertSame('PLAN-INS-001', $subscription->provider_plan_id);
        $this->assertNull($subscription->provider_subscription_id);

        // Same first_name+last_name / phone_country_code+phone_number merge
        // logic as AccommodationOrderRequest, verified independently here
        // since AccommodationInstallmentOrderRequest is a separate class.
        $this->assertSame('Jane Doe', $booking->guest_name);
        $this->assertSame('+62 812345678', $booking->guest_phone);
        $this->assertSame('Indonesia', $booking->guest_country);

        Http::assertSent(function ($request) {
            if ($request->url() !== 'https://api-m.sandbox.paypal.com/v1/billing/plans') {
                return false;
            }

            return $request['payment_preferences']['payment_failure_threshold'] === 5;
        });
    }

    public function test_create_installment_subscription_response_includes_a_start_time_matching_next_due_at(): void
    {
        $roomType = $this->createBookableRoomType();
        $accommodation = $roomType->accommodation;
        $accommodation->update(['installment_enabled' => true]);
        $this->fakePayPalPlanCreation('PROD-START-001', 'PLAN-START-001');

        $response = $this->postJson(
            route('stay.installments.store', $accommodation),
            $this->validInstallmentPayload($roomType, checkInDays: 300),
        );

        $response->assertOk();

        $subscription = AccommodationPaymentSubscription::query()
            ->where('accommodation_booking_id', $response->json('booking_id'))
            ->firstOrFail();

        $this->assertNotNull($subscription->next_due_at);
        $this->assertGreaterThan(2, $subscription->installment_count);

        $expectedStartTime = $subscription->next_due_at->copy()->utc()->startOfDay()->format('Y-m-d\TH:i:s\Z');

        $response->assertJsonPath('paypal_subscription_start_time', $expectedStartTime);

        // Regression guard for the double-billing bug: start_time must be
        // strictly later than "now" (the setup_fee/first-payment moment),
        // not the same day — otherwise PayPal bills the first REGULAR cycle
        // almost immediately after the setup_fee instead of a month later.
        $this->assertTrue(
            \Illuminate\Support\Carbon::parse($expectedStartTime)->greaterThan(now()->addDays(20)),
        );
    }

    public function test_create_installment_subscription_with_minimum_two_installments_includes_correct_start_time(): void
    {
        $roomType = $this->createBookableRoomType();
        $accommodation = $roomType->accommodation;
        $accommodation->update(['installment_enabled' => true]);
        $this->fakePayPalPlanCreation('PROD-START-002', 'PLAN-START-002');

        $payload = array_merge(
            $this->validInstallmentPayload($roomType, checkInDays: 300),
            ['installment_count' => 2],
        );

        $response = $this->postJson(route('stay.installments.store', $accommodation), $payload);

        $response->assertOk();
        $response->assertJsonPath('installment_plan.installment_count', 2);

        $subscription = AccommodationPaymentSubscription::query()
            ->where('accommodation_booking_id', $response->json('booking_id'))
            ->firstOrFail();

        $this->assertSame(2, $subscription->installment_count);
        $this->assertNotNull($subscription->next_due_at);

        $expectedStartTime = $subscription->next_due_at->copy()->utc()->startOfDay()->format('Y-m-d\TH:i:s\Z');

        $response->assertJsonPath('paypal_subscription_start_time', $expectedStartTime);
    }

    public function test_create_installment_subscription_returns_422_when_not_enough_billing_dates_fit(): void
    {
        $roomType = $this->createBookableRoomType();
        $roomType->accommodation()->update(['installment_enabled' => true]);
        $accommodation = $roomType->accommodation;

        $response = $this->postJson(
            route('stay.installments.store', $accommodation),
            $this->validInstallmentPayload($roomType, checkInDays: 10),
        );

        $response->assertStatus(422);
        $response->assertJsonPath('status', 'not_eligible');

        Http::assertNothingSent();

        $booking = AccommodationBooking::query()->latest('id')->first();
        $this->assertSame(AccommodationBooking::STATUS_CANCELLED, $booking->status);
    }

    public function test_create_installment_subscription_returns_409_when_room_is_fully_booked(): void
    {
        $roomType = $this->createBookableRoomType(totalRooms: 1);
        $roomType->accommodation()->update(['installment_enabled' => true]);
        $accommodation = $roomType->accommodation;

        AccommodationBooking::factory()
            ->confirmed()
            ->create([
                'accommodation_id' => $accommodation->id,
                'accommodation_room_type_id' => $roomType->id,
                'check_in_date' => now()->addDays(90)->toDateString(),
                'check_out_date' => now()->addDays(92)->toDateString(),
            ]);

        $response = $this->postJson(
            route('stay.installments.store', $accommodation),
            $this->validInstallmentPayload($roomType, checkInDays: 90),
        );

        $response->assertStatus(409);
        $response->assertJsonPath('status', 'unavailable');

        Http::assertNothingSent();
    }

    public function test_approve_installment_subscription_records_provider_subscription_id_without_confirming_booking(): void
    {
        $roomType = $this->createBookableRoomType();
        $roomType->accommodation()->update(['installment_enabled' => true]);
        $accommodation = $roomType->accommodation;
        $this->fakePayPalPlanCreation('PROD-INS-002', 'PLAN-INS-002');

        $createResponse = $this->postJson(
            route('stay.installments.store', $accommodation),
            $this->validInstallmentPayload($roomType, checkInDays: 90),
        );

        $approveResponse = $this->postJson($createResponse->json('approve_url'), [
            'provider_subscription_id' => 'I-SUBSCRIPTION-001',
        ]);

        $approveResponse->assertOk();
        $approveResponse->assertJsonPath('status', 'approval_attached');
        $approveResponse->assertJsonPath('provider_subscription_id', 'I-SUBSCRIPTION-001');
        $approveResponse->assertJsonPath('awaiting_webhook', true);

        $booking = AccommodationBooking::query()->findOrFail($createResponse->json('booking_id'));
        $this->assertSame(AccommodationBooking::STATUS_PENDING_PAYMENT, $booking->status);

        $subscription = AccommodationPaymentSubscription::query()
            ->where('accommodation_booking_id', $booking->id)
            ->firstOrFail();
        $this->assertSame(AccommodationPaymentSubscription::STATUS_APPROVAL_PENDING, $subscription->status);
        $this->assertSame('I-SUBSCRIPTION-001', $subscription->provider_subscription_id);
    }

    public function test_cancel_installment_subscription_releases_the_hold(): void
    {
        $roomType = $this->createBookableRoomType();
        $roomType->accommodation()->update(['installment_enabled' => true]);
        $accommodation = $roomType->accommodation;
        $this->fakePayPalPlanCreation('PROD-INS-003', 'PLAN-INS-003');

        $createResponse = $this->postJson(
            route('stay.installments.store', $accommodation),
            $this->validInstallmentPayload($roomType, checkInDays: 90),
        );

        $cancelResponse = $this->postJson($createResponse->json('cancel_url'));

        $cancelResponse->assertOk();
        $cancelResponse->assertJsonPath('status', 'cancelled');

        $booking = AccommodationBooking::query()->findOrFail($createResponse->json('booking_id'));
        $this->assertSame(AccommodationBooking::STATUS_CANCELLED, $booking->status);

        $subscription = AccommodationPaymentSubscription::query()
            ->where('accommodation_booking_id', $booking->id)
            ->firstOrFail();
        $this->assertSame(AccommodationPaymentSubscription::STATUS_CANCELLED, $subscription->status);
    }

    public function test_create_response_includes_a_status_url_that_reflects_pending_state(): void
    {
        $roomType = $this->createBookableRoomType();
        $accommodation = $roomType->accommodation;
        $accommodation->update(['installment_enabled' => true]);
        $this->fakePayPalPlanCreation('PROD-INS-004', 'PLAN-INS-004');

        $createResponse = $this->postJson(
            route('stay.installments.store', $accommodation),
            $this->validInstallmentPayload($roomType, checkInDays: 90),
        );

        $statusUrl = $createResponse->json('status_url');
        $this->assertNotEmpty($statusUrl);

        $statusResponse = $this->getJson($statusUrl);

        $statusResponse->assertOk();
        $statusResponse->assertJsonPath('status', 'draft');
        $statusResponse->assertJsonPath('redirect_url', null);
    }

    public function test_status_endpoint_returns_confirmed_and_redirect_url_once_booking_is_confirmed(): void
    {
        $roomType = $this->createBookableRoomType();
        $accommodation = $roomType->accommodation;
        $accommodation->update(['installment_enabled' => true]);
        $this->fakePayPalPlanCreation('PROD-INS-005', 'PLAN-INS-005');

        $createResponse = $this->postJson(
            route('stay.installments.store', $accommodation),
            $this->validInstallmentPayload($roomType, checkInDays: 90),
        );

        $booking = AccommodationBooking::query()->findOrFail($createResponse->json('booking_id'));
        $booking->update(['status' => AccommodationBooking::STATUS_CONFIRMED, 'paid_at' => now()]);

        $statusResponse = $this->getJson($createResponse->json('status_url'));

        $statusResponse->assertOk();
        $statusResponse->assertJsonPath('status', 'confirmed');
        $this->assertNotEmpty($statusResponse->json('redirect_url'));
    }

    private function fakePayPalPlanCreation(string $productId, string $planId): void
    {
        Http::fake([
            'https://api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response([
                'access_token' => 'paypal-access-token',
            ]),
            'https://api-m.sandbox.paypal.com/v1/catalogs/products' => Http::response([
                'id' => $productId,
            ], 201),
            'https://api-m.sandbox.paypal.com/v1/billing/plans' => Http::response([
                'id' => $planId,
                'status' => 'ACTIVE',
            ], 201),
            'https://api-m.sandbox.paypal.com/v1/billing/subscriptions/*/cancel' => Http::response([], 204),
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
    private function validInstallmentPayload(AccommodationRoomType $roomType, int $checkInDays): array
    {
        return [
            'room_type_id' => $roomType->id,
            'check_in_date' => now()->addDays($checkInDays)->toDateString(),
            'check_out_date' => now()->addDays($checkInDays + 2)->toDateString(),
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'guest_email' => 'jane@example.com',
            'phone_country_code' => '+62',
            'phone_number' => '812345678',
            'country' => 'Indonesia',
        ];
    }
}
