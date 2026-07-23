<?php

namespace App\Services\Payments;

use App\Models\EmailLog;
use App\Models\PaymentSubscription;
use App\Models\User;
use App\Services\EmailNotificationService;
use App\Support\EmailNotificationTypeRegistry;
use Illuminate\Support\Facades\DB;

class OverdueInstallmentService
{
    public const NOTIFICATION_TYPE_OVERDUE_INACTIVE = EmailNotificationTypeRegistry::INSTALLMENT_OVERDUE_INACTIVE;
    public const REFERENCE_TYPE_PAYMENT_SUBSCRIPTION = 'payment_subscription';

    public function __construct(
        private readonly EmailNotificationService $emailNotificationService,
    ) {}

    /**
     * @return array{processed:int,deactivated:int,notifications_sent:int,skipped:int}
     */
    public function sync(): array
    {
        $processed = 0;
        $deactivated = 0;
        $notificationsSent = 0;
        $skipped = 0;

        PaymentSubscription::query()
            ->with(['user', 'invoice.user', 'package', 'accessTier'])
            ->where('status', PaymentSubscription::STATUS_PAST_DUE)
            ->whereNotNull('grace_deadline_at')
            ->where('grace_deadline_at', '<=', now())
            ->orderBy('id')
            ->chunkById(100, function ($subscriptions) use (&$processed, &$deactivated, &$notificationsSent, &$skipped): void {
                foreach ($subscriptions as $subscription) {
                    $processed++;

                    $result = DB::transaction(function () use ($subscription): array {
                        /** @var PaymentSubscription $subscription */
                        $subscription = PaymentSubscription::query()
                            ->with(['user', 'invoice.user', 'package', 'accessTier'])
                            ->lockForUpdate()
                            ->findOrFail($subscription->id);

                        $user = $subscription->user ?? $subscription->invoice?->user;
                        if (! $user instanceof User) {
                            return [
                                'deactivated' => false,
                                'notification_sent' => false,
                                'skipped' => true,
                            ];
                        }

                        $wasActive = (bool) $user->is_active;
                        if ($wasActive) {
                            $user->forceFill([
                                'is_active' => false,
                            ])->save();
                        }

                        $sentBefore = $this->hasSentOverdueNotification($subscription);
                        if (! $sentBefore) {
                            $this->emailNotificationService->sendAutomated(
                                self::NOTIFICATION_TYPE_OVERDUE_INACTIVE,
                                $this->notificationPayload($subscription, $user),
                                self::REFERENCE_TYPE_PAYMENT_SUBSCRIPTION,
                                $subscription->id,
                            );
                        }

                        $sentAfter = $this->hasSentOverdueNotification($subscription);

                        $metadata = $subscription->metadata ?? [];
                        if ($wasActive) {
                            $metadata['overdue_deactivated_at'] = now()->toIso8601String();
                        }
                        if (! $sentBefore && $sentAfter) {
                            $metadata['overdue_notification_sent_at'] = now()->toIso8601String();
                        }

                        $subscription->forceFill([
                            'metadata' => $metadata,
                            'last_synced_at' => now(),
                        ])->save();

                        return [
                            'deactivated' => $wasActive,
                            'notification_sent' => ! $sentBefore && $sentAfter,
                            'skipped' => false,
                        ];
                    });

                    $deactivated += $result['deactivated'] ? 1 : 0;
                    $notificationsSent += $result['notification_sent'] ? 1 : 0;
                    $skipped += $result['skipped'] ? 1 : 0;
                }
            });

        return [
            'processed' => $processed,
            'deactivated' => $deactivated,
            'notifications_sent' => $notificationsSent,
            'skipped' => $skipped,
        ];
    }

    private function hasSentOverdueNotification(PaymentSubscription $subscription): bool
    {
        return EmailLog::query()
            ->where('notification_type', self::NOTIFICATION_TYPE_OVERDUE_INACTIVE)
            ->where('reference_type', self::REFERENCE_TYPE_PAYMENT_SUBSCRIPTION)
            ->where('reference_id', $subscription->id)
            ->where('recipient_type', 'admin')
            ->where('status', 'sent')
            ->exists();
    }

    /**
     * @return array<string, string>
     */
    private function notificationPayload(PaymentSubscription $subscription, User $user): array
    {
        return [
            'notification_type' => self::NOTIFICATION_TYPE_OVERDUE_INACTIVE,
            'user_name' => $user->name,
            'user_email' => $user->email,
            'admin_email' => config('mail.from.address'),
            'package_title' => $subscription->package?->title ?? '',
            'tier_name' => $subscription->accessTier?->name ?? '',
            'access_tier' => $subscription->accessTier?->slug ?? '',
            'access_tier_label' => $subscription->accessTier?->name ?? '',
            'invoice_number' => (string) ($subscription->invoice?->invoice_number ?? ''),
            'provider_subscription_id' => (string) ($subscription->provider_subscription_id ?? ''),
            'next_due_at' => optional($subscription->next_due_at)->toDateString() ?? '',
            'grace_deadline_at' => optional($subscription->grace_deadline_at)->toDateString() ?? '',
            'final_due_at' => optional($subscription->final_due_at)->toDateString() ?? '',
            'amount_due' => (string) ($subscription->invoice?->balance_due ?? $subscription->next_billing_amount ?? ''),
            'balance_due' => (string) ($subscription->invoice?->balance_due ?? $subscription->next_billing_amount ?? ''),
            'currency_code' => (string) $subscription->currency_code,
            'student_status' => 'inactive',
            'payment_completed_at' => optional($subscription->completed_at)->toDateTimeString() ?? '',
        ];
    }
}
