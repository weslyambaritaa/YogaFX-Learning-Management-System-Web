<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpgradePaymentRequest;
use App\Models\AccessTier;
use App\Models\PaymentActivity;
use App\Services\SimulatedPaymentFlowService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class UpgradeController extends Controller
{
    public function __construct(
        private readonly SimulatedPaymentFlowService $paymentFlow,
    ) {}

    public function show(AccessTier $accessTier): Response
    {
        $user = request()->user();
        abort_unless($user && $user->isStudent(), 403);

        $currentPrice = (float) ($user->accessTier?->price_amount ?? 0);
        $targetPrice = (float) $accessTier->price_amount;
        abort_if(! $accessTier->is_active || $targetPrice <= $currentPrice, 404);

        $totalPaid = (float) PaymentActivity::query()
            ->where('user_id', $user->id)
            ->where('status', PaymentActivity::STATUS_SUCCESS)
            ->sum('amount');

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
                    'price_amount' => (float) $user->accessTier->price_amount,
                ] : null,
                'target_tier' => [
                    'id' => $accessTier->id,
                    'name' => $accessTier->name,
                    'slug' => $accessTier->slug,
                    'price_amount' => $targetPrice,
                ],
            ],
        ]);
    }

    public function pay(UpgradePaymentRequest $request, AccessTier $accessTier): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && $user->isStudent(), 403);

        $this->paymentFlow->processUpgrade($user, $accessTier, $request->validated());

        return redirect()
            ->route('student.dashboard')
            ->with('status', 'upgrade-payment-success');
    }
}
