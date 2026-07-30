<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Accommodation;
use App\Models\AccommodationBooking;
use App\Models\AccommodationPaymentSubscription;
use App\Services\Payments\PayPalSubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class AccommodationBookingController extends Controller
{
    public function __construct(
        private readonly PayPalSubscriptionService $paypalSubscriptionService,
    ) {}

    public function index(Request $request): Response
    {
        $search = trim((string) $request->input('search', ''));

        $validStatuses = [
            'all',
            AccommodationBooking::STATUS_PENDING_PAYMENT,
            AccommodationBooking::STATUS_CONFIRMED,
            AccommodationBooking::STATUS_CANCELLED,
            AccommodationBooking::STATUS_EXPIRED,
        ];
        $status = (string) $request->input('status', 'all');
        $status = in_array($status, $validStatuses, true) ? $status : 'all';

        $accommodationId = (string) $request->input('accommodation_id', '');
        $dateFrom = (string) $request->input('date_from', '');
        $dateTo = (string) $request->input('date_to', '');

        $perPage = (int) $request->integer('per_page', 15);
        $perPage = in_array($perPage, [15, 25, 50], true) ? $perPage : 15;

        $bookings = AccommodationBooking::query()
            ->with(['accommodation:id,title', 'roomType:id,title'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($nested) use ($search): void {
                    $nested->where('guest_name', 'ilike', '%'.$search.'%')
                        ->orWhere('guest_email', 'ilike', '%'.$search.'%')
                        ->orWhere('booking_number', 'ilike', '%'.$search.'%');
                });
            })
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->when($accommodationId !== '', fn ($query) => $query->where('accommodation_id', $accommodationId))
            ->when($dateFrom !== '', fn ($query) => $query->whereDate('check_in_date', '>=', $dateFrom))
            ->when($dateTo !== '', fn ($query) => $query->whereDate('check_out_date', '<=', $dateTo))
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();

        $bookings->setCollection(
            $bookings->getCollection()->map(fn (AccommodationBooking $booking) => $this->presentBookingRow($booking)),
        );

        return Inertia::render('Admin/AccommodationBookings/Index', [
            'bookings' => $bookings,
            'accommodations' => Accommodation::query()
                ->orderBy('title')
                ->get(['id', 'title']),
            'filters' => [
                'search' => $search,
                'status' => $status,
                'accommodation_id' => $accommodationId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'per_page' => $perPage,
            ],
            'status' => session('status'),
        ]);
    }

    public function show(AccommodationBooking $booking): Response
    {
        $booking->loadMissing(['accommodation', 'roomType', 'user:id,name,email']);

        return Inertia::render('Admin/AccommodationBookings/Show', [
            'booking' => $this->presentBookingDetail($booking),
            'status' => session('status'),
        ]);
    }

    public function cancel(AccommodationBooking $booking): RedirectResponse
    {
        if ($booking->status !== AccommodationBooking::STATUS_CONFIRMED) {
            return redirect()
                ->route('admin.accommodation-bookings.show', $booking)
                ->withErrors([
                    'booking' => 'Only confirmed bookings can be cancelled from here.',
                ]);
        }

        $this->cancelActiveInstallmentSubscription($booking);

        $booking->update([
            'status' => AccommodationBooking::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ]);

        return redirect()
            ->route('admin.accommodation-bookings.show', $booking)
            ->with('status', 'booking-cancelled');
    }

    /**
     * Per spec §8: cancelling a booking that is still mid-installment must
     * also cancel its PayPal subscription, so the guest is not billed for a
     * booking that no longer exists. Best-effort — a PayPal failure here
     * must not block the admin from cancelling the booking itself.
     */
    private function cancelActiveInstallmentSubscription(AccommodationBooking $booking): void
    {
        $activeStatuses = [
            AccommodationPaymentSubscription::STATUS_DRAFT,
            AccommodationPaymentSubscription::STATUS_APPROVAL_PENDING,
            AccommodationPaymentSubscription::STATUS_ACTIVE,
            AccommodationPaymentSubscription::STATUS_PAST_DUE,
        ];

        $subscription = AccommodationPaymentSubscription::query()
            ->where('accommodation_booking_id', $booking->id)
            ->whereIn('status', $activeStatuses)
            ->latest('id')
            ->first();

        if (! $subscription instanceof AccommodationPaymentSubscription) {
            return;
        }

        if (is_string($subscription->provider_subscription_id) && $subscription->provider_subscription_id !== '') {
            try {
                $this->paypalSubscriptionService->cancelSubscription(
                    $subscription->provider_subscription_id,
                    'Booking cancelled by admin.',
                );
            } catch (Throwable $throwable) {
                Log::error('Failed to cancel the PayPal subscription for an admin-cancelled accommodation booking. The subscription may keep billing the guest — manual PayPal follow-up required.', [
                    'booking_id' => $booking->id,
                    'booking_number' => $booking->booking_number,
                    'accommodation_payment_subscription_id' => $subscription->id,
                    'provider_subscription_id' => $subscription->provider_subscription_id,
                    'exception' => $throwable->getMessage(),
                ]);

                report($throwable);
            }
        }

        $subscription->forceFill([
            'status' => AccommodationPaymentSubscription::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ])->save();
    }

    /**
     * @return array<string, mixed>
     */
    private function presentBookingRow(AccommodationBooking $booking): array
    {
        return [
            'id' => $booking->id,
            'booking_number' => $booking->booking_number,
            'accommodation_title' => $booking->accommodation?->title ?? '—',
            'room_type_title' => $booking->roomType?->title ?? '—',
            'guest_name' => $booking->guest_name,
            'guest_email' => $booking->guest_email,
            'guest_country' => $booking->guest_country,
            'check_in_date' => $booking->check_in_date->toDateString(),
            'check_out_date' => $booking->check_out_date->toDateString(),
            'nights' => $booking->nights,
            'total_amount' => (float) $booking->total_amount,
            'currency_code' => $booking->currency_code,
            'status' => $booking->status,
            'paid_at' => $booking->paid_at?->toDateTimeString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentBookingDetail(AccommodationBooking $booking): array
    {
        return [
            'id' => $booking->id,
            'booking_number' => $booking->booking_number,
            'status' => $booking->status,
            'accommodation' => $booking->accommodation ? [
                'id' => $booking->accommodation->id,
                'title' => $booking->accommodation->title,
            ] : null,
            'room_type' => $booking->roomType ? [
                'id' => $booking->roomType->id,
                'title' => $booking->roomType->title,
            ] : null,
            'user' => $booking->user ? [
                'id' => $booking->user->id,
                'name' => $booking->user->name,
                'email' => $booking->user->email,
            ] : null,
            'guest_name' => $booking->guest_name,
            'guest_email' => $booking->guest_email,
            'guest_phone' => $booking->guest_phone,
            'guest_country' => $booking->guest_country,
            'check_in_date' => $booking->check_in_date->toDateString(),
            'check_out_date' => $booking->check_out_date->toDateString(),
            'nights' => $booking->nights,
            'price_per_night' => (float) $booking->price_per_night,
            'total_amount' => (float) $booking->total_amount,
            'currency_code' => $booking->currency_code,
            'paypal_order_id' => $booking->paypal_order_id,
            'hold_expires_at' => $booking->hold_expires_at?->toDateTimeString(),
            'paid_at' => $booking->paid_at?->toDateTimeString(),
            'cancelled_at' => $booking->cancelled_at?->toDateTimeString(),
            'created_at' => $booking->created_at?->toDateTimeString(),
        ];
    }
}
