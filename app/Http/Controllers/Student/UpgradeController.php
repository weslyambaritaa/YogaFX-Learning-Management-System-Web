<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpgradePaymentRequest;
use App\Models\AccessTier;
use App\Models\Invoice;
use App\Models\PaymentSubscription;
use App\Services\PaymentCheckoutService;
use App\Services\Payments\PaymentSubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

class UpgradeController extends Controller
{
    public function __construct(
        private readonly PaymentCheckoutService $paymentFlow,
        private readonly PaymentSubscriptionService $paymentSubscriptionService,
    ) {}

    public function show(AccessTier $accessTier): Response
    {
        $user = request()->user();
        abort_unless($user && $user->isStudent(), 403);

        $currentLevel = (int) ($user->accessTier?->level ?? 0);
        abort_if(! $accessTier->is_active || (int) $accessTier->level <= $currentLevel, 404);

        return Inertia::render('Student/Upgrade/Checkout', [
            'upgrade' => $this->paymentFlow->upgradePayload(
                $user,
                $accessTier,
                request()->integer('package_id') ?: null,
            ),
        ]);
    }

    public function pay(UpgradePaymentRequest $request, AccessTier $accessTier): RedirectResponse|HttpResponse|JsonResponse
    {
        $user = $request->user();
        abort_unless($user && $user->isStudent(), 403);

        $validated = $request->validated();
        $result = $this->paymentFlow->startUpgradeCheckout($user, $accessTier, $validated);

        if (($validated['payment_type'] ?? null) === Invoice::PAYMENT_TYPE_INSTALLMENT) {
            /** @var PaymentSubscription|null $paymentSubscription */
            $paymentSubscription = $result['payment_subscription'] ?? null;
            abort_unless($paymentSubscription instanceof PaymentSubscription, 409, 'Subscription checkout data is unavailable.');

            return response()->json([
                'status' => 'prepared',
                'flow' => 'subscription',
                'invoice_id' => $result['invoice']->id,
                'payment_subscription_id' => $paymentSubscription->id,
                'provider_plan_id' => $paymentSubscription->provider_plan_id,
                'provider_subscription_id' => $paymentSubscription->provider_subscription_id,
                'billing_day' => $paymentSubscription->billing_day,
                'paypal_subscription_start_time' => $this->paypalSubscriptionStartTime($paymentSubscription),
                'status_url' => $this->paymentFlow->upgradeSubscriptionStatusUrl($accessTier),
            ]);
        }

        if ($request->header('X-Inertia')) {
            return Inertia::location($result['redirect_url']);
        }

        return redirect()->away($result['redirect_url']);
    }

    public function success(Invoice $invoice): Response|RedirectResponse
    {
        abort_unless($invoice->type === Invoice::TYPE_UPGRADE, 404);

        if (! in_array($invoice->status, [Invoice::STATUS_PAID_FULL, Invoice::STATUS_INSTALLMENT], true)) {
            return redirect()->route('login')->with('status', 'Upgrade payment success is not available for this invoice.');
        }

        $invoice->loadMissing('accessTier', 'user.accessTier');

        return Inertia::render('Student/Upgrade/Success', [
            'upgrade' => [
                'invoice_number' => $invoice->invoice_number,
                'status' => $invoice->status,
                'amount_paid' => (float) $invoice->paymentActivities()
                    ->where('status', \App\Models\Payment::STATUS_SUCCESS)
                    ->sum('amount_paid'),
                'currency_code' => $invoice->currency_code,
                'continue_url' => route('student.dashboard'),
                'cta_label' => 'Return to Dashboard',
                'target_tier' => [
                    'name' => $invoice->accessTier?->name,
                    'slug' => $invoice->accessTier?->slug,
                ],
                'student' => [
                    'name' => $invoice->user?->name,
                    'email' => $invoice->user?->email,
                ],
            ],
        ]);
    }

    public function approveInstallment(Request $request, AccessTier $accessTier): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && $user->isStudent(), 403);

        $validated = $request->validate([
            'payment_subscription_id' => ['required', 'integer'],
            'provider_subscription_id' => ['required', 'string', 'max:255'],
        ]);

        /** @var PaymentSubscription $paymentSubscription */
        $paymentSubscription = PaymentSubscription::query()
            ->with('invoice')
            ->where('user_id', $user->id)
            ->where('access_tier_id', $accessTier->id)
            ->findOrFail($validated['payment_subscription_id']);

        $paymentSubscription = $this->paymentSubscriptionService->attachApprovedUpgradeSubscription(
            $user,
            $paymentSubscription,
            (string) $validated['provider_subscription_id'],
        );

        return response()->json([
            'status' => 'approval_attached',
            'flow' => 'subscription',
            'invoice_id' => $paymentSubscription->invoice_id,
            'payment_subscription_id' => $paymentSubscription->id,
            'provider_subscription_id' => $paymentSubscription->provider_subscription_id,
            'awaiting_payment_confirmation' => true,
            'status_url' => $this->paymentFlow->upgradeSubscriptionStatusUrl($accessTier),
            'message' => 'PayPal approval received. Waiting for the first payment confirmation.',
        ]);
    }

    public function installmentStatus(AccessTier $accessTier): JsonResponse
    {
        $user = request()->user();
        abort_unless($user && $user->isStudent(), 403);

        /** @var PaymentSubscription|null $paymentSubscription */
        $paymentSubscription = PaymentSubscription::query()
            ->with('invoice')
            ->where('user_id', $user->id)
            ->where('access_tier_id', $accessTier->id)
            ->whereHas('invoice', fn ($query) => $query->where('type', Invoice::TYPE_UPGRADE))
            ->latest('id')
            ->first();

        if (
            $paymentSubscription instanceof PaymentSubscription
            && $paymentSubscription->invoice instanceof Invoice
            && in_array($paymentSubscription->invoice->status, [Invoice::STATUS_PAID_FULL, Invoice::STATUS_INSTALLMENT], true)
        ) {
            return response()->json([
                'status' => 'upgrade_ready',
                'upgrade_ready' => true,
                'redirect_url' => $this->paymentFlow->upgradePaymentSuccessUrl($paymentSubscription->invoice),
                'message' => 'Your first installment payment has been confirmed.',
            ]);
        }

        $message = app()->environment('local')
            ? 'Waiting for the first PayPal payment webhook before activating the upgraded tier.'
            : 'Waiting for the first PayPal payment confirmation from PayPal.';

        return response()->json([
            'status' => $paymentSubscription?->provider_subscription_id ? 'waiting_for_first_payment' : 'approval_required',
            'upgrade_ready' => false,
            'redirect_url' => null,
            'payment_subscription_id' => $paymentSubscription?->id,
            'payment_subscription_status' => $paymentSubscription?->status,
            'message' => $message,
        ]);
    }

    public function subscriptionReturn(AccessTier $accessTier): RedirectResponse
    {
        return redirect()
            ->route('student.upgrades.show', $accessTier)
            ->with('status', 'Your PayPal installment approval was received. We are waiting for payment confirmation.');
    }

    public function subscriptionCancel(AccessTier $accessTier): RedirectResponse
    {
        $user = request()->user();
        abort_unless($user && $user->isStudent(), 403);

        $subscriptionId = (string) request()->query('subscription_id', '');

        if ($subscriptionId !== '') {
            $paymentSubscription = PaymentSubscription::query()
                ->where('user_id', $user->id)
                ->where('access_tier_id', $accessTier->id)
                ->where('provider_subscription_id', $subscriptionId)
                ->latest('id')
                ->first();

            if ($paymentSubscription instanceof PaymentSubscription) {
                $this->paymentSubscriptionService->markApprovalCancelled($paymentSubscription);
            }
        }

        return redirect()
            ->route('student.upgrades.show', $accessTier)
            ->withErrors(['payment_method' => 'The PayPal installment checkout was cancelled.']);
    }

    private function paypalSubscriptionStartTime(PaymentSubscription $paymentSubscription): ?string
    {
        if (! $paymentSubscription->next_due_at) {
            return null;
        }

        return $paymentSubscription->next_due_at
            ->copy()
            ->utc()
            ->startOfDay()
            ->format('Y-m-d\TH:i:s\Z');
    }
}
