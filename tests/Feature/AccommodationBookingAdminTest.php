<?php

namespace Tests\Feature;

use App\Models\Accommodation;
use App\Models\AccommodationBooking;
use App\Models\AccommodationPaymentSubscription;
use App\Models\AccommodationRoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AccommodationBookingAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.paypal.base_url', 'https://api-m.sandbox.paypal.com');
        config()->set('services.paypal.client_id', 'client-id');
        config()->set('services.paypal.secret', 'client-secret');
    }

    public function test_admin_can_view_bookings_index(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $roomType = $this->createRoomType();

        AccommodationBooking::factory()
            ->confirmed()
            ->create([
                'accommodation_id' => $roomType->accommodation_id,
                'accommodation_room_type_id' => $roomType->id,
            ]);

        $this->actingAs($admin)
            ->get(route('admin.accommodation-bookings.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/AccommodationBookings/Index')
                ->has('bookings.data', 1));
    }

    public function test_admin_can_filter_bookings_by_status(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $roomType = $this->createRoomType();

        AccommodationBooking::factory()
            ->confirmed()
            ->create([
                'accommodation_id' => $roomType->accommodation_id,
                'accommodation_room_type_id' => $roomType->id,
            ]);
        AccommodationBooking::factory()
            ->cancelled()
            ->create([
                'accommodation_id' => $roomType->accommodation_id,
                'accommodation_room_type_id' => $roomType->id,
            ]);

        $this->actingAs($admin)
            ->get(route('admin.accommodation-bookings.index', ['status' => 'confirmed']))
            ->assertInertia(fn ($page) => $page
                ->has('bookings.data', 1)
                ->where('bookings.data.0.status', 'confirmed'));
    }

    // NOTE: the search filter (guest_name/guest_email/booking_number) uses
    // `ilike`, which is Postgres-only — same as the existing StudentController
    // search. SQLite (this test suite's driver) throws a syntax error on
    // `ilike`, so — like every other admin search filter in this codebase —
    // it isn't covered by an automated test here. See the phase report for
    // the full note; not something introduced by this change.

    public function test_admin_can_view_booking_detail(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $roomType = $this->createRoomType();

        $booking = AccommodationBooking::factory()
            ->confirmed()
            ->create([
                'accommodation_id' => $roomType->accommodation_id,
                'accommodation_room_type_id' => $roomType->id,
            ]);

        $this->actingAs($admin)
            ->get(route('admin.accommodation-bookings.show', $booking))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/AccommodationBookings/Show')
                ->where('booking.booking_number', $booking->booking_number));
    }

    public function test_admin_can_cancel_a_confirmed_booking(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $roomType = $this->createRoomType();

        $booking = AccommodationBooking::factory()
            ->confirmed()
            ->create([
                'accommodation_id' => $roomType->accommodation_id,
                'accommodation_room_type_id' => $roomType->id,
            ]);

        $this->actingAs($admin)
            ->post(route('admin.accommodation-bookings.cancel', $booking))
            ->assertRedirect(route('admin.accommodation-bookings.show', $booking));

        $booking->refresh();
        $this->assertSame(AccommodationBooking::STATUS_CANCELLED, $booking->status);
        $this->assertNotNull($booking->cancelled_at);
    }

    public function test_admin_cancelling_a_booking_also_cancels_its_active_installment_subscription(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $roomType = $this->createRoomType();

        $booking = AccommodationBooking::factory()
            ->confirmed()
            ->create([
                'accommodation_id' => $roomType->accommodation_id,
                'accommodation_room_type_id' => $roomType->id,
            ]);

        $subscription = AccommodationPaymentSubscription::query()->create([
            'accommodation_booking_id' => $booking->id,
            'provider' => AccommodationPaymentSubscription::PROVIDER_PAYPAL,
            'provider_product_id' => 'PROD-ADMIN-001',
            'provider_plan_id' => 'PLAN-ADMIN-001',
            'provider_subscription_id' => 'I-ADMIN-CANCEL-001',
            'status' => AccommodationPaymentSubscription::STATUS_ACTIVE,
            'installment_count' => 3,
            'installments_paid_count' => 1,
            'currency_code' => 'USD',
            'total_amount' => 300,
            'monthly_amount' => 100,
            'first_payment_amount' => 100,
        ]);

        Http::fake([
            'https://api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response([
                'access_token' => 'paypal-access-token',
            ]),
            'https://api-m.sandbox.paypal.com/v1/billing/subscriptions/I-ADMIN-CANCEL-001/cancel' => Http::response([], 204),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.accommodation-bookings.cancel', $booking))
            ->assertRedirect(route('admin.accommodation-bookings.show', $booking));

        $booking->refresh();
        $this->assertSame(AccommodationBooking::STATUS_CANCELLED, $booking->status);

        $subscription->refresh();
        $this->assertSame(AccommodationPaymentSubscription::STATUS_CANCELLED, $subscription->status);
        $this->assertNotNull($subscription->cancelled_at);

        Http::assertSent(fn ($request) => $request->url() === 'https://api-m.sandbox.paypal.com/v1/billing/subscriptions/I-ADMIN-CANCEL-001/cancel');
    }

    public function test_admin_cancelling_a_booking_still_succeeds_when_paypal_cancel_call_fails(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $roomType = $this->createRoomType();

        $booking = AccommodationBooking::factory()
            ->confirmed()
            ->create([
                'accommodation_id' => $roomType->accommodation_id,
                'accommodation_room_type_id' => $roomType->id,
            ]);

        $subscription = AccommodationPaymentSubscription::query()->create([
            'accommodation_booking_id' => $booking->id,
            'provider' => AccommodationPaymentSubscription::PROVIDER_PAYPAL,
            'provider_product_id' => 'PROD-ADMIN-002',
            'provider_plan_id' => 'PLAN-ADMIN-002',
            'provider_subscription_id' => 'I-ADMIN-CANCEL-002',
            'status' => AccommodationPaymentSubscription::STATUS_PAST_DUE,
            'installment_count' => 3,
            'installments_paid_count' => 1,
            'currency_code' => 'USD',
            'total_amount' => 300,
            'monthly_amount' => 100,
            'first_payment_amount' => 100,
        ]);

        Http::fake([
            'https://api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response([
                'access_token' => 'paypal-access-token',
            ]),
            'https://api-m.sandbox.paypal.com/v1/billing/subscriptions/I-ADMIN-CANCEL-002/cancel' => Http::response([
                'message' => 'Subscription not found.',
            ], 404),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.accommodation-bookings.cancel', $booking))
            ->assertRedirect(route('admin.accommodation-bookings.show', $booking));

        $booking->refresh();
        $this->assertSame(AccommodationBooking::STATUS_CANCELLED, $booking->status);

        // Best-effort: even though the PayPal call failed, our own records
        // still reflect the cancellation and the admin action itself succeeded.
        $subscription->refresh();
        $this->assertSame(AccommodationPaymentSubscription::STATUS_CANCELLED, $subscription->status);
    }

    public function test_admin_cannot_cancel_a_booking_that_is_not_confirmed(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $roomType = $this->createRoomType();

        $booking = AccommodationBooking::factory()
            ->pendingPayment()
            ->create([
                'accommodation_id' => $roomType->accommodation_id,
                'accommodation_room_type_id' => $roomType->id,
            ]);

        $this->actingAs($admin)
            ->post(route('admin.accommodation-bookings.cancel', $booking))
            ->assertSessionHasErrors('booking');

        $booking->refresh();
        $this->assertSame(AccommodationBooking::STATUS_PENDING_PAYMENT, $booking->status);
    }

    public function test_student_cannot_access_admin_booking_screens(): void
    {
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);

        $this->actingAs($student)
            ->get(route('admin.accommodation-bookings.index'))
            ->assertForbidden();
    }

    private function createRoomType(): AccommodationRoomType
    {
        $accommodation = Accommodation::factory()->create(['is_active' => true]);

        return AccommodationRoomType::factory()->create([
            'accommodation_id' => $accommodation->id,
        ]);
    }
}
