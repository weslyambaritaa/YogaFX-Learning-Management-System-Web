<?php

namespace App\Services\Invoices;

use App\Models\Invoice;
use App\Models\OnboardingState;
use App\Models\Payment;
use App\Models\PaymentSubscription;
use App\Models\User;
use Carbon\CarbonImmutable;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class InvoiceConfirmationPdfService
{
    /**
     * @return array{name: string, mime: string, data: string}
     */
    public function makeAttachment(Invoice $invoice): array
    {
        $invoice = $invoice->fresh([
            'user.accessTier',
            'pendingRegistration.accessTier',
            'accessTier',
            'package',
            'payments' => fn ($query) => $query->orderBy('id'),
            'paymentSubscriptions' => fn ($query) => $query->orderByDesc('id'),
        ]) ?? $invoice;

        $paymentSubscription = $invoice->paymentSubscriptions->first();
        $successPayments = $invoice->payments
            ->filter(fn (Payment $payment) => $payment->status === Payment::STATUS_SUCCESS)
            ->values();

        $studentName = $this->studentName($invoice);
        $courseName = $invoice->package?->title
            ?: ($invoice->accessTier?->name ?: ($invoice->user?->accessTier?->name ?: 'YogaFX Program'));
        $view = $invoice->payment_type === Invoice::PAYMENT_TYPE_INSTALLMENT
            ? 'pdf.installment-confirmation'
            : 'pdf.online-confirmation';

        $html = view($view, [
            'documentTitle' => $invoice->invoice_number,
            'courseName' => $courseName,
            'dearName' => $studentName,
            'coursePrice' => $this->formatMoney(
                amount: (float) $invoice->total_amount,
                currencyCode: $invoice->currency_code,
                decimals: $this->summaryDecimals((float) $invoice->total_amount),
            ),
            'fullPaymentAmount' => $this->formatMoney(
                amount: $successPayments->sum(fn (Payment $payment) => (float) $payment->amount_paid),
                currencyCode: $invoice->currency_code,
                decimals: $this->summaryDecimals($successPayments->sum(fn (Payment $payment) => (float) $payment->amount_paid)),
            ),
            'paymentReceivedOn' => $this->paymentReceivedOn($successPayments),
            ...$this->installmentViewData($invoice, $paymentSubscription, $successPayments),
        ])->render();

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return [
            'name' => $this->fileName($invoice, $studentName),
            'mime' => 'application/pdf',
            'data' => $dompdf->output(),
        ];
    }

    public function paymentSuccessNextStepUrl(Invoice $invoice, ?OnboardingState $onboardingState = null): string
    {
        if ($invoice->type === Invoice::TYPE_INITIAL && $onboardingState instanceof OnboardingState) {
            return \URL::temporarySignedRoute(
                'onboarding.enrollment.show',
                now()->addDays(7),
                ['onboardingState' => $onboardingState->id],
            );
        }

        if ($invoice->type === Invoice::TYPE_UPGRADE) {
            return \URL::temporarySignedRoute(
                'student.upgrades.success',
                now()->addDays(7),
                ['invoice' => $invoice->id],
            );
        }

        return route('login');
    }

    private function studentName(Invoice $invoice): string
    {
        $user = $invoice->user;

        if ($user instanceof User && trim((string) $user->name) !== '') {
            return trim((string) $user->name);
        }

        return trim(implode(' ', array_filter([
            $invoice->pendingRegistration?->first_name,
            $invoice->pendingRegistration?->last_name,
        ]))) ?: 'Student';
    }

    private function fileName(Invoice $invoice, string $studentName): string
    {
        $safeName = Str::of($studentName)->lower()->slug('-')->value();
        $prefix = $invoice->payment_type === Invoice::PAYMENT_TYPE_INSTALLMENT
            ? 'installment-confirmation'
            : 'online-confirmation';

        return sprintf('%s-%s.pdf', $prefix, $safeName !== '' ? $safeName : 'student');
    }

    /**
     * @param  Collection<int, Payment>  $successPayments
     * @return array{
     *     firstInstallmentAmount: string,
     *     balanceDue: string,
     *     firstInstallmentReceivedOn: string,
     *     showBalanceScheduleCopy: bool,
     *     installmentRows: array<int, array{
     *         label: string,
     *         amount: string,
     *         show_status: bool,
     *         status_label: string,
     *         date_label: string,
     *         date_value: string
     *     }>
     * }
     */
    private function installmentViewData(
        Invoice $invoice,
        ?PaymentSubscription $subscription,
        Collection $successPayments,
    ): array {
        if ($invoice->payment_type !== Invoice::PAYMENT_TYPE_INSTALLMENT) {
            return [
                'firstInstallmentAmount' => '',
                'balanceDue' => '',
                'firstInstallmentReceivedOn' => '',
                'showBalanceScheduleCopy' => false,
                'installmentRows' => [],
            ];
        }

        $rows = $this->buildInstallmentRows($invoice, $subscription, $successPayments);
        $firstPaymentAmount = $subscription?->first_payment_amount;

        if ($firstPaymentAmount === null) {
            $firstPaymentAmount = (float) ($successPayments->first()?->amount_paid ?? 0);
        }

        $balanceDueAmount = max(0, round((float) $invoice->total_amount - (float) $firstPaymentAmount, 2));

        return [
            'firstInstallmentAmount' => $this->formatMoney(
                amount: (float) $firstPaymentAmount,
                currencyCode: $invoice->currency_code,
                decimals: $this->detailDecimals((float) $firstPaymentAmount),
            ),
            'balanceDue' => $this->formatMoney(
                amount: $balanceDueAmount,
                currencyCode: $invoice->currency_code,
                decimals: $this->detailDecimals($balanceDueAmount),
            ),
            'firstInstallmentReceivedOn' => $rows[0]['date_value'] ?? $this->paymentReceivedOn($successPayments),
            'showBalanceScheduleCopy' => $balanceDueAmount > 0,
            'installmentRows' => $rows,
        ];
    }

    /**
     * @param  Collection<int, Payment>  $successPayments
     * @return array<int, array{
     *     label: string,
     *     amount: string,
     *     show_status: bool,
     *     status_label: string,
     *     date_label: string,
     *     date_value: string
     * }>
     */
    private function buildInstallmentRows(
        Invoice $invoice,
        ?PaymentSubscription $subscription,
        Collection $successPayments,
    ): array {
        $scheduleBreakdown = [];
        $metadata = is_array($subscription?->metadata) ? $subscription->metadata : [];
        $installmentPlan = is_array($metadata['installment_plan'] ?? null) ? $metadata['installment_plan'] : [];

        if (is_array($installmentPlan['schedule_breakdown'] ?? null)) {
            $scheduleBreakdown = array_values(array_filter(
                $installmentPlan['schedule_breakdown'],
                fn ($row) => is_array($row) && isset($row['cycle_number'], $row['amount'], $row['due_at']),
            ));
        }

        if ($scheduleBreakdown === [] && $subscription instanceof PaymentSubscription) {
            $installmentCount = max(1, (int) $subscription->installment_count);
            $scheduleBreakdown[] = [
                'cycle_number' => 1,
                'amount' => (float) $subscription->first_payment_amount,
                'due_at' => optional($subscription->first_payment_paid_at ?? $subscription->started_at ?? $invoice->issued_at)->toDateString()
                    ?? now()->toDateString(),
            ];

            for ($cycle = 2; $cycle <= $installmentCount; $cycle++) {
                $amount = (float) ($subscription->next_billing_amount ?: $subscription->monthly_base_amount ?: 0);
                $dueAt = $this->fallbackRecurringDate($subscription, $cycle);

                $scheduleBreakdown[] = [
                    'cycle_number' => $cycle,
                    'amount' => $amount,
                    'due_at' => $dueAt->toDateString(),
                ];
            }
        }

        if ($scheduleBreakdown === []) {
            $paidAt = $successPayments->first()?->updated_at?->toDateString() ?? now()->toDateString();

            return [[
                'label' => '1st Installment',
                'amount' => $this->formatMoney(
                    amount: (float) ($successPayments->first()?->amount_paid ?? 0),
                    currencyCode: $invoice->currency_code,
                    decimals: 2,
                ),
                'show_status' => false,
                'status_label' => '',
                'date_label' => 'Received on',
                'date_value' => $this->humanDate($paidAt),
            ]];
        }

        $rows = [];
        $successfulPayments = $successPayments->values();

        foreach ($scheduleBreakdown as $index => $scheduleRow) {
            $payment = $successfulPayments->get($index);
            $isPaid = $payment instanceof Payment;
            $dateSource = $isPaid
                ? optional($payment->updated_at)->toDateString()
                : (string) $scheduleRow['due_at'];

            $rows[] = [
                'label' => sprintf('%s Installment', $this->ordinal((int) $scheduleRow['cycle_number'])),
                'amount' => $this->formatMoney(
                    amount: (float) $scheduleRow['amount'],
                    currencyCode: $invoice->currency_code,
                    decimals: 2,
                ),
                'show_status' => $index > 0,
                'status_label' => $isPaid ? 'Paid' : 'Scheduled',
                'date_label' => $isPaid ? 'Received on' : 'Due on',
                'date_value' => $this->humanDate($dateSource),
            ];
        }

        return $rows;
    }

    private function fallbackRecurringDate(PaymentSubscription $subscription, int $cycle): CarbonImmutable
    {
        $start = CarbonImmutable::parse(
            optional($subscription->first_payment_paid_at ?? $subscription->started_at ?? $subscription->created_at)->toDateString()
                ?? now()->toDateString()
        );

        return $start->addMonthsNoOverflow(max(0, $cycle - 1))
            ->startOfMonth()
            ->setDay((int) ($subscription->billing_day ?: 1));
    }

    /**
     * @param  Collection<int, Payment>  $successPayments
     */
    private function paymentReceivedOn(Collection $successPayments): string
    {
        $paidAt = $successPayments->last()?->updated_at?->toDateString()
            ?? $successPayments->first()?->updated_at?->toDateString()
            ?? now()->toDateString();

        return $this->humanDate($paidAt);
    }

    private function humanDate(string $value): string
    {
        return CarbonImmutable::parse($value)->format('M, jS Y');
    }

    private function ordinal(int $number): string
    {
        if ($number % 100 >= 11 && $number % 100 <= 13) {
            return $number.'th';
        }

        return match ($number % 10) {
            1 => $number.'st',
            2 => $number.'nd',
            3 => $number.'rd',
            default => $number.'th',
        };
    }

    private function summaryDecimals(float $amount): int
    {
        return abs($amount - round($amount)) < 0.00001 ? 0 : 2;
    }

    private function detailDecimals(float $amount): int
    {
        return abs($amount - round($amount)) < 0.00001 ? 0 : 2;
    }

    private function formatMoney(float|int|string|null $amount, ?string $currencyCode, int $decimals = 2): string
    {
        $numeric = (float) ($amount ?? 0);
        $currency = strtoupper((string) ($currencyCode ?: 'USD'));
        $symbol = match ($currency) {
            'USD' => '$',
            'GBP' => '£',
            'EUR' => '€',
            'AUD' => 'A$',
            'CAD' => 'C$',
            'IDR' => 'Rp',
            default => '',
        };

        return trim(sprintf(
            '%s %s%s',
            $currency,
            $symbol,
            number_format($numeric, $decimals, '.', ','),
        ));
    }
}
