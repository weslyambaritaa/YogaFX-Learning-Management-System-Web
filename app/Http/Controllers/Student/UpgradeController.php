<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpgradePaymentRequest;
use App\Models\AccessTier;
use App\Models\Invoice;
use App\Services\PaymentCheckoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

class UpgradeController extends Controller
{
    public function __construct(
        private readonly PaymentCheckoutService $paymentFlow,
    ) {}

    public function show(AccessTier $accessTier): Response
    {
        $user = request()->user();
        abort_unless($user && $user->isStudent(), 403);

        $currentLevel = (int) ($user->accessTier?->level ?? 0);
        $targetPrice = (float) $accessTier->price;
        abort_if(! $accessTier->is_active || (int) $accessTier->level <= $currentLevel, 404);

        $totalPaid = $this->paymentFlow->relevantUpgradePaidAmount($user, $accessTier);
        $amountDue = max(0, round($targetPrice - $totalPaid, 2));

        return Inertia::render('Student/Upgrade/Checkout', [
            'upgrade' => [
                'submit_url' => route('student.upgrades.pay', $accessTier),
                'amount_due' => $amountDue,
                'total_paid' => $totalPaid,
                'current_tier' => $user->accessTier ? [
                    'id' => $user->accessTier->id,
                    'name' => $user->accessTier->name,
                    'slug' => $user->accessTier->slug,
                    'price' => (float) $user->accessTier->price,
                    'currency_code' => $user->accessTier->currency_code,
                    'level' => $user->accessTier->level,
                ] : null,
                'target_tier' => [
                    'id' => $accessTier->id,
                    'name' => $accessTier->name,
                    'slug' => $accessTier->slug,
                    'price' => $targetPrice,
                    'currency_code' => $accessTier->currency_code,
                    'level' => $accessTier->level,
                ],
                'payment_method_options' => $this->paymentFlow->availablePaymentMethodOptions(),
            ],
        ]);
    }

    public function pay(UpgradePaymentRequest $request, AccessTier $accessTier): RedirectResponse|HttpResponse
    {
        $user = $request->user();
        abort_unless($user && $user->isStudent(), 403);

        $result = $this->paymentFlow->startUpgradeCheckout($user, $accessTier, $request->validated());

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
}
