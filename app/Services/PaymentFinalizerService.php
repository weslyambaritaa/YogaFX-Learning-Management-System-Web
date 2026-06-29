<?php

namespace App\Services;

use App\Jobs\SendOnboardingContinuationEmailJob;
use App\Jobs\SendUpgradeWelcomeEmailJob;
use App\Models\AccessTier;
use App\Models\Invoice;
use App\Models\OnboardingState;
use App\Models\Payment;
use App\Models\PendingRegistration;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class PaymentFinalizerService
{
    /**
     * @return array{invoice: Invoice, payment_activity: Payment, user: ?User, onboarding_state: ?OnboardingState, skipped: bool}
     */
    public function finalizeSuccessfulPayment(
        Payment $paymentActivity,
        ?string $paymentReference = null,
    ): array {
        return DB::transaction(function () use ($paymentActivity, $paymentReference): array {
            /** @var Payment $paymentActivity */
            $paymentActivity = Payment::query()
                ->with(['invoice.pendingRegistration.accessTier', 'invoice.user.accessTier'])
                ->lockForUpdate()
                ->findOrFail($paymentActivity->id);

            /** @var Invoice $invoice */
            $invoice = Invoice::query()
                ->with(['pendingRegistration.accessTier', 'user.accessTier'])
                ->lockForUpdate()
                ->findOrFail($paymentActivity->invoice_id);

            $reference = $paymentReference ?: $paymentActivity->payment_reference;
            $onboardingState = $invoice->type === Invoice::TYPE_INITIAL
                ? OnboardingState::query()->where('pending_registration_id', $invoice->pending_registration_id)->first()
                : null;

            if ($paymentActivity->status === Payment::STATUS_SUCCESS) {
                return [
                    'invoice' => $invoice,
                    'payment_activity' => $paymentActivity,
                    'user' => $invoice->user,
                    'onboarding_state' => $onboardingState,
                    'skipped' => true,
                ];
            }

            if ($invoice->status === Invoice::STATUS_PAID_FULL) {
                return [
                    'invoice' => $invoice,
                    'payment_activity' => $paymentActivity,
                    'user' => $invoice->user,
                    'onboarding_state' => $onboardingState,
                    'skipped' => true,
                ];
            }

            if (
                is_string($reference)
                && Payment::query()
                    ->where('payment_reference', $reference)
                    ->where('status', Payment::STATUS_SUCCESS)
                    ->whereKeyNot($paymentActivity->id)
                    ->exists()
            ) {
                return [
                    'invoice' => $invoice,
                    'payment_activity' => $paymentActivity,
                    'user' => $invoice->user,
                    'onboarding_state' => $onboardingState,
                    'skipped' => true,
                ];
            }

            $hasPreviousSuccessfulActivity = Payment::query()
                ->where('invoice_id', $invoice->id)
                ->where('status', Payment::STATUS_SUCCESS)
                ->whereKeyNot($paymentActivity->id)
                ->exists();

            $paymentActivity->forceFill([
                'payment_reference' => $reference,
                'status' => Payment::STATUS_SUCCESS,
            ])->save();

            $remainingBalance = max(0, round((float) $invoice->balance_due - (float) $paymentActivity->amount_paid, 2));
            $isPaidFull = $remainingBalance <= 0;

            $invoice->forceFill([
                'balance_due' => $remainingBalance,
                'status' => $isPaidFull ? Invoice::STATUS_PAID_FULL : Invoice::STATUS_INSTALLMENT,
                'paid_at' => $isPaidFull ? now() : $invoice->paid_at,
            ])->save();

            $user = null;
            $onboardingState = null;

            if ($invoice->type === Invoice::TYPE_INITIAL) {
                [$user, $onboardingState] = $this->finalizeInitialInvoice($invoice);
            } else {
                $user = $this->finalizeUpgradeInvoice($invoice, ! $hasPreviousSuccessfulActivity);
            }

            return [
                'invoice' => $invoice->fresh(['pendingRegistration.accessTier', 'user.accessTier']),
                'payment_activity' => $paymentActivity->fresh(),
                'user' => $user?->fresh(['accessTier']),
                'onboarding_state' => $onboardingState?->fresh(['user', 'pendingRegistration.accessTier']),
                'skipped' => false,
            ];
        });
    }

    public function cancelPendingPayment(Payment $paymentActivity): Payment
    {
        return DB::transaction(function () use ($paymentActivity): Payment {
            /** @var Payment $paymentActivity */
            $paymentActivity = Payment::query()
                ->lockForUpdate()
                ->findOrFail($paymentActivity->id);

            if ($paymentActivity->status === Payment::STATUS_PENDING) {
                $paymentActivity->forceFill([
                    'status' => Payment::STATUS_CANCELLED,
                ])->save();
            }

            return $paymentActivity->fresh();
        });
    }

    public function failPendingPayment(Payment $paymentActivity, ?string $notes = null): Payment
    {
        return DB::transaction(function () use ($paymentActivity, $notes): Payment {
            /** @var Payment $paymentActivity */
            $paymentActivity = Payment::query()
                ->lockForUpdate()
                ->findOrFail($paymentActivity->id);

            if ($paymentActivity->status !== Payment::STATUS_SUCCESS) {
                $paymentActivity->forceFill([
                    'status' => Payment::STATUS_FAILED,
                    'notes' => $notes ?: $paymentActivity->notes,
                ])->save();
            }

            return $paymentActivity->fresh();
        });
    }

    public function keepPaymentPending(Payment $paymentActivity, ?string $notes = null): Payment
    {
        return DB::transaction(function () use ($paymentActivity, $notes): Payment {
            /** @var Payment $paymentActivity */
            $paymentActivity = Payment::query()
                ->lockForUpdate()
                ->findOrFail($paymentActivity->id);

            if ($paymentActivity->status !== Payment::STATUS_SUCCESS) {
                $paymentActivity->forceFill([
                    'status' => Payment::STATUS_PENDING,
                    'notes' => $notes ?: $paymentActivity->notes,
                ])->save();
            }

            return $paymentActivity->fresh();
        });
    }

    /**
     * @return array{0: User, 1: OnboardingState}
     */
    private function finalizeInitialInvoice(Invoice $invoice): array
    {
        /** @var PendingRegistration $pendingRegistration */
        $pendingRegistration = $invoice->pendingRegistration()->with('accessTier')->firstOrFail();
        $user = $invoice->user;

        if (! $user) {
            $user = User::query()
                ->whereRaw('LOWER(email) = ?', [Str::lower($pendingRegistration->email)])
                ->first();
        }

        if (! $user) {
            $user = User::query()->create([
                'name' => $pendingRegistration->fullName(),
                'role' => User::ROLE_STUDENT,
                'is_active' => false,
                'access_tier_id' => $invoice->access_tier_id,
                'email' => Str::lower($pendingRegistration->email),
                'password' => Hash::make(Str::random(40)),
                'first_name' => $pendingRegistration->first_name,
                'last_name' => $pendingRegistration->last_name,
                'whatsapp' => $pendingRegistration->phone,
                'country' => $pendingRegistration->country,
            ]);
        } else {
            $user->forceFill([
                'access_tier_id' => $invoice->access_tier_id,
            ])->save();
        }

        if ($invoice->user_id !== $user->id) {
            $invoice->forceFill([
                'user_id' => $user->id,
            ])->save();
        }

        $pendingRegistration->forceFill([
            'status' => PendingRegistration::STATUS_PAYMENT_SUCCESS,
            'payment_succeeded_at' => $pendingRegistration->payment_succeeded_at ?? now(),
        ])->save();

        $onboardingState = OnboardingState::query()->firstOrCreate(
            ['pending_registration_id' => $pendingRegistration->id],
            [
                'user_id' => $user->id,
                'status' => OnboardingState::STATUS_AWAITING_ENROLLMENT,
                'continuation_sent_at' => now(),
            ],
        );

        $shouldSendContinuationEmail = false;

        if ($onboardingState->user_id !== $user->id) {
            $onboardingState->forceFill([
                'user_id' => $user->id,
            ])->save();
        }

        if ($onboardingState->continuation_sent_at === null) {
            $onboardingState->forceFill([
                'continuation_sent_at' => now(),
            ])->save();

            $shouldSendContinuationEmail = true;
        }

        if ($onboardingState->wasRecentlyCreated) {
            $shouldSendContinuationEmail = true;
        }

        if ($shouldSendContinuationEmail) {
            SendOnboardingContinuationEmailJob::dispatch(
                $this->continuationEmailPayload($user, $onboardingState),
                $onboardingState->id,
            );
        }

        return [$user, $onboardingState];
    }

    private function finalizeUpgradeInvoice(Invoice $invoice, bool $shouldSendUpgradeEmail): User
    {
        /** @var User $user */
        $user = $invoice->user()->firstOrFail();
        $targetTier = $invoice->accessTier()->first();
        $basisInvoice = $this->relevantUpgradeBasisInvoice($invoice, $user, $targetTier);

        if ($user->access_tier_id !== $invoice->access_tier_id) {
            $user->forceFill([
                'access_tier_id' => $invoice->access_tier_id,
            ])->save();
        }

        if ($basisInvoice && $basisInvoice->id !== $invoice->id && $basisInvoice->status !== Invoice::STATUS_UPGRADED) {
            $basisInvoice->forceFill([
                'status' => Invoice::STATUS_UPGRADED,
            ])->save();
        }

        if ($shouldSendUpgradeEmail) {
            SendUpgradeWelcomeEmailJob::dispatch(
                $user->id,
                $targetTier?->name ?? 'Upgraded Tier',
            );
        }

        return $user;
    }

    private function relevantUpgradeBasisInvoice(Invoice $upgradeInvoice, User $user, ?AccessTier $targetTier): ?Invoice
    {
        $currentTier = $user->accessTier;

        $query = Invoice::query()
            ->where('user_id', $user->id)
            ->whereKeyNot($upgradeInvoice->id)
            ->whereIn('status', [
                Invoice::STATUS_PAID_FULL,
                Invoice::STATUS_INSTALLMENT,
            ]);

        if ($currentTier) {
            $query->where('access_tier_id', $currentTier->id);
        } elseif ($targetTier) {
            $query->whereHas('accessTier', fn ($tierQuery) => $tierQuery->where('level', '<', $targetTier->level));
        }

        return $query
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function continuationEmailPayload(User $user, OnboardingState $onboardingState): array
    {
        return [
            'user_name' => $user->name,
            'user_email' => $user->email,
            'admin_email' => config('mail.from.address'),
            'access_tier' => $user->accessTier?->slug,
            'access_tier_label' => $user->accessTier?->name,
            'registration_date' => optional($user->created_at)->toDateString() ?? now()->toDateString(),
            'dashboard_url' => route('login'),
            'login_url' => route('login'),
            'continuation_url' => $this->enrollmentUrl($onboardingState),
        ];
    }

    private function enrollmentUrl(OnboardingState $onboardingState): string
    {
        return URL::temporarySignedRoute(
            'onboarding.enrollment.show',
            now()->addDays(7),
            ['onboardingState' => $onboardingState->id],
        );
    }
}
