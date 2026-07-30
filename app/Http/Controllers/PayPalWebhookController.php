<?php

namespace App\Http\Controllers;

use App\Models\AccommodationPaymentSubscription;
use App\Models\Payment;
use App\Services\Accommodations\AccommodationInstallmentWebhookHandler;
use App\Services\Accommodations\AccommodationPaymentSubscriptionEventLogService;
use App\Services\PayPalService;
use App\Services\PaymentFinalizerService;
use App\Services\Payments\InstallmentWebhookHandler;
use App\Services\Payments\PayPalSubscriptionService;
use App\Services\Payments\PaymentSubscriptionEventLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PayPalWebhookController extends Controller
{
    public function __construct(
        private readonly PayPalService $paypalService,
        private readonly PaymentFinalizerService $paymentFinalizer,
        private readonly PayPalSubscriptionService $payPalSubscriptionService,
        private readonly PaymentSubscriptionEventLogService $paymentSubscriptionEventLogService,
        private readonly InstallmentWebhookHandler $installmentWebhookHandler,
        private readonly AccommodationPaymentSubscriptionEventLogService $accommodationPaymentSubscriptionEventLogService,
        private readonly AccommodationInstallmentWebhookHandler $accommodationInstallmentWebhookHandler,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->all();
        $headers = [
            'paypal-auth-algo' => $request->header('paypal-auth-algo'),
            'paypal-cert-url' => $request->header('paypal-cert-url'),
            'paypal-transmission-id' => $request->header('paypal-transmission-id'),
            'paypal-transmission-sig' => $request->header('paypal-transmission-sig'),
            'paypal-transmission-time' => $request->header('paypal-transmission-time'),
        ];

        abort_unless($this->paypalService->verifyWebhookSignature($payload, $headers), 401, 'Invalid PayPal webhook signature.');

        $eventType = is_string($payload['event_type'] ?? null) ? $payload['event_type'] : null;

        if ($this->payPalSubscriptionService->isSubscriptionWebhookEvent($eventType)) {
            /*
            |--------------------------------------------------------------------------
            | Accommodation vs Package subscription routing
            |--------------------------------------------------------------------------
            |
            | Both domains share this one PayPal webhook endpoint/signature (PayPal
            | is configured with a single webhook URL). A given provider_subscription_id
            | only ever belongs to one domain's table, so this lookup safely decides
            | routing without touching the Package path below at all — that branch
            | is byte-for-byte the same code that ran before this addition.
            */
            $subscriptionId = $this->payPalSubscriptionService->extractWebhookReferences($payload)['subscription_id'];

            if (
                is_string($subscriptionId) && $subscriptionId !== ''
                && AccommodationPaymentSubscription::query()->where('provider_subscription_id', $subscriptionId)->exists()
            ) {
                $logged = $this->accommodationPaymentSubscriptionEventLogService->logPayPalWebhookEvent($payload);

                if (! $logged['duplicate']) {
                    $this->accommodationInstallmentWebhookHandler->handle($logged['event']);
                }

                return response()->json([
                    'status' => $logged['duplicate'] ? 'duplicate' : 'processed',
                ]);
            }

            $logged = $this->paymentSubscriptionEventLogService->logPayPalWebhookEvent($payload);

            if (! $logged['duplicate']) {
                $this->installmentWebhookHandler->handle($logged['event']);
            }

            return response()->json([
                'status' => $logged['duplicate'] ? 'duplicate' : 'processed',
            ]);
        }

        $reference = $this->paypalService->extractWebhookOrderReference($payload);
        $orderId = $reference['order_id'];
        $eventType = $reference['event_type'];

        if (! is_string($orderId) || $orderId === '') {
            return response()->json(['status' => 'ignored'], 202);
        }

        /** @var Payment|null $paymentActivity */
        $paymentActivity = Payment::query()
            ->where('payment_reference', $orderId)
            ->latest('id')
            ->first();

        if (! $paymentActivity) {
            return response()->json(['status' => 'ignored'], 202);
        }

        if ($eventType === 'CHECKOUT.ORDER.APPROVED' && $paymentActivity->status !== Payment::STATUS_SUCCESS) {
            $this->paypalService->captureOrder($orderId);
        }

        $this->paymentFinalizer->finalizeSuccessfulPayment($paymentActivity, $orderId);

        return response()->json(['status' => 'ok']);
    }
}
