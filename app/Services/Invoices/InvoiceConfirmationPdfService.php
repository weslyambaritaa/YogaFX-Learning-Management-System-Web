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
        $payload = [
            'document_title' => (string) $invoice->invoice_number,
            'course_name' => $this->courseName($invoice),
            'dear_name' => $studentName,
            'course_price' => $this->formatMoney(
                amount: (float) $invoice->total_amount,
                currencyCode: $invoice->currency_code,
                decimals: $this->summaryDecimals((float) $invoice->total_amount),
            ),
            'full_payment_amount' => $this->formatMoney(
                amount: $successPayments->sum(fn (Payment $payment) => (float) $payment->amount_paid),
                currencyCode: $invoice->currency_code,
                decimals: $this->summaryDecimals($successPayments->sum(fn (Payment $payment) => (float) $payment->amount_paid)),
            ),
            'payment_received_on' => $this->paymentReceivedOn($successPayments),
            ...$this->installmentViewData($invoice, $paymentSubscription, $successPayments),
        ];

        $html = $invoice->payment_type === Invoice::PAYMENT_TYPE_INSTALLMENT
            ? $this->renderInstallmentConfirmationHtml($payload)
            : $this->renderOnlineConfirmationHtml($payload);

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

    private function courseName(Invoice $invoice): string
    {
        return (string) (
            $invoice->package?->title
            ?: $invoice->accessTier?->name
            ?: $invoice->user?->accessTier?->name
            ?: 'YogaFX Program'
        );
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
     *         status_first: bool,
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

        $balanceDueAmount = max(0, round((float) $invoice->balance_due, 2));

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
     *     status_first: bool,
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
                'status_first' => false,
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
                'status_first' => $isPaid,
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
            'GBP' => "\u{00A3}",
            'EUR' => "\u{20AC}",
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

    /**
     * @param  array{
     *     document_title: string,
     *     course_name: string,
     *     dear_name: string,
     *     course_price: string,
     *     full_payment_amount: string,
     *     payment_received_on: string
     * }  $payload
     */
    private function renderOnlineConfirmationHtml(array $payload): string
    {
        $documentTitle = $this->escape($payload['document_title']);
        $courseName = $this->escape($payload['course_name']);
        $dearName = $this->escape($payload['dear_name']);
        $coursePrice = $this->escape($payload['course_price']);
        $fullPaymentAmount = $this->escape($payload['full_payment_amount']);
        $paymentReceivedOn = $this->escape($payload['payment_received_on']);

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{$documentTitle}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #14213d; margin: 0; background: #f7f3ec; }
        .page { position: relative; padding: 34px 42px 34px; min-height: 1020px; overflow: hidden; }
        .watermark {
            position: absolute;
            top: 190px;
            right: -20px;
            font-size: 88px;
            font-weight: 700;
            color: rgba(177, 138, 80, 0.07);
            transform: rotate(-90deg);
            letter-spacing: 0.18em;
        }
        .sheet {
            position: relative;
            background: #fffdfa;
            border: 1px solid #d8c7a9;
            padding: 28px 30px 30px;
            box-shadow: 0 14px 34px rgba(26, 35, 56, 0.08);
        }
        .top-band {
            height: 12px;
            margin: -28px -30px 24px;
            background: linear-gradient(90deg, #14213d 0%, #1f3a5f 55%, #c49a56 100%);
        }
        .academy {
            font-size: 10px;
            letter-spacing: 0.34em;
            text-transform: uppercase;
            color: #8d6b36;
            margin: 0 0 12px;
        }
        .meta-row {
            margin: 0 0 10px;
            overflow: hidden;
        }
        .meta-left {
            float: left;
            width: 72%;
        }
        .meta-right {
            float: right;
            width: 92px;
            height: 92px;
            border: 2px solid #c49a56;
            border-radius: 999px;
            text-align: center;
            color: #8d6b36;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.16em;
            text-transform: uppercase;
            padding-top: 20px;
            box-sizing: border-box;
            background: rgba(255, 248, 236, 0.92);
        }
        .meta-right span {
            display: block;
            margin-top: 6px;
            font-size: 28px;
            line-height: 1;
            letter-spacing: 0;
            color: #10203b;
        }
        .title { font-size: 25px; font-weight: 700; line-height: 1.18; margin: 0 0 3px; max-width: 620px; color: #10203b; }
        .subtitle { font-size: 23px; font-weight: 700; margin: 0 0 20px; color: #10203b; }
        .divider {
            width: 98px;
            height: 3px;
            background: #c49a56;
            margin: 0 0 24px;
        }
        .paragraph { font-size: 16px; line-height: 1.68; margin: 0 0 15px; color: #20314e; }
        .paragraph strong { color: #10203b; }
        .investment-box {
            margin: 20px 0 18px;
            padding: 16px 18px 14px;
            border: 1px solid #e2d6c1;
            background: linear-gradient(180deg, #fffdfa 0%, #fbf5ea 100%);
        }
        .label { font-size: 14px; font-weight: 700; margin: 0 0 6px; text-transform: uppercase; letter-spacing: 0.08em; color: #7f5c28; }
        .amount { font-size: 28px; font-weight: 700; margin: 0; color: #10203b; }
        .payment-line {
            font-size: 18px;
            font-weight: 700;
            line-height: 1.6;
            margin: 0 0 18px;
            padding: 13px 16px;
            border-left: 4px solid #c49a56;
            background: #f7f1e7;
            color: #10203b;
        }
        .footer {
            font-size: 15px;
            line-height: 1.75;
            margin-top: 24px;
            color: #33415c;
            border-top: 1px solid #e5d8c6;
            padding-top: 16px;
        }
        .signature {
            margin-top: 22px;
            width: 210px;
            border-top: 1px solid #bda47e;
            padding-top: 7px;
            font-size: 12px;
            color: #7c6b52;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="watermark">YOGAFX</div>
        <div class="sheet">
            <div class="top-band"></div>
            <p class="academy">YogaFX International Yoga Teacher Training Academy</p>
            <div class="meta-row">
                <div class="meta-left">
                    <h1 class="title">{$courseName}</h1>
                    <div class="subtitle">Confirmation</div>
                </div>
                <div class="meta-right">Confirmed<span>✓</span></div>
            </div>
            <div class="divider"></div>
            <p class="paragraph">Dear {$dearName},</p>
            <p class="paragraph">We are thrilled that you will be joining us for our <strong>{$courseName}</strong>.</p>
            <div class="investment-box">
                <p class="label">Course Investment</p>
                <p class="amount">{$coursePrice}</p>
            </div>
            <p class="payment-line">Full Payment Received: {$fullPaymentAmount} Received on {$paymentReceivedOn}</p>
            <p class="footer">Thank you for your interest in YogaFX International Yoga Teacher Training Academy<br>it really is appreciated</p>
            <div class="signature">YogaFX Admissions Team</div>
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * @param  array{
     *     document_title: string,
     *     course_name: string,
     *     dear_name: string,
     *     course_price: string,
     *     firstInstallmentAmount: string,
     *     balanceDue: string,
     *     firstInstallmentReceivedOn: string,
     *     showBalanceScheduleCopy: bool,
     *     installmentRows: array<int, array{
     *         label: string,
     *         amount: string,
     *         show_status: bool,
     *         status_label: string,
     *         status_first: bool,
     *         date_label: string,
     *         date_value: string
     *     }>
     * }  $payload
     */
    private function renderInstallmentConfirmationHtml(array $payload): string
    {
        $documentTitle = $this->escape($payload['document_title']);
        $courseName = $this->escape($payload['course_name']);
        $dearName = $this->escape($payload['dear_name']);
        $coursePrice = $this->escape($payload['course_price']);
        $firstInstallmentAmount = $this->escape($payload['firstInstallmentAmount']);
        $balanceDue = $this->escape($payload['balanceDue']);
        $firstInstallmentReceivedOn = $this->escape($payload['firstInstallmentReceivedOn']);
        $balanceCopy = $payload['showBalanceScheduleCopy']
            ? '<br>'.$balanceDue.' due as follows please'
            : '';

        $rowsHtml = '';

        foreach ($payload['installmentRows'] as $row) {
            $label = $this->escape($row['label']);
            $amount = $this->escape($row['amount']);
            $status = $this->escape($row['status_label']);
            $dateLine = $this->escape($row['date_label'].' '.$row['date_value']);
            $statusClass = $row['status_label'] === 'Paid' ? 'status-paid' : 'status-scheduled';
            $topLine = $row['show_status'] && $row['status_first']
                ? '<p class="installment-status '.$statusClass.'">'.$status.'</p>'
                : '';
            $bottomLine = $row['show_status'] && ! $row['status_first']
                ? '<p class="installment-status '.$statusClass.'">'.$status.'</p>'
                : '';

            $rowsHtml .= <<<HTML
<tr>
    <td class="schedule-left">
        <p class="installment-label">{$label}</p>
        <p class="installment-amount">{$amount}</p>
    </td>
    <td class="schedule-right">
        {$topLine}
        <p class="installment-date">{$dateLine}</p>
        {$bottomLine}
    </td>
</tr>
HTML;
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{$documentTitle}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #14213d; margin: 0; background: #f7f3ec; }
        .page { position: relative; padding: 34px 42px 34px; min-height: 1020px; overflow: hidden; }
        .watermark {
            position: absolute;
            top: 220px;
            right: -20px;
            font-size: 88px;
            font-weight: 700;
            color: rgba(177, 138, 80, 0.07);
            transform: rotate(-90deg);
            letter-spacing: 0.18em;
        }
        .sheet {
            position: relative;
            background: #fffdfa;
            border: 1px solid #d8c7a9;
            padding: 28px 30px 30px;
            box-shadow: 0 14px 34px rgba(26, 35, 56, 0.08);
        }
        .top-band {
            height: 12px;
            margin: -28px -30px 24px;
            background: linear-gradient(90deg, #14213d 0%, #1f3a5f 55%, #c49a56 100%);
        }
        .academy {
            font-size: 10px;
            letter-spacing: 0.34em;
            text-transform: uppercase;
            color: #8d6b36;
            margin: 0 0 12px;
        }
        .meta-row {
            margin: 0 0 10px;
            overflow: hidden;
        }
        .meta-left {
            float: left;
            width: 72%;
        }
        .meta-right {
            float: right;
            width: 92px;
            height: 92px;
            border: 2px solid #c49a56;
            border-radius: 999px;
            text-align: center;
            color: #8d6b36;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.16em;
            text-transform: uppercase;
            padding-top: 20px;
            box-sizing: border-box;
            background: rgba(255, 248, 236, 0.92);
        }
        .meta-right span {
            display: block;
            margin-top: 6px;
            font-size: 28px;
            line-height: 1;
            letter-spacing: 0;
            color: #10203b;
        }
        .title { font-size: 25px; font-weight: 700; line-height: 1.18; margin: 0 0 3px; max-width: 620px; color: #10203b; }
        .subtitle { font-size: 23px; font-weight: 700; margin: 0 0 20px; color: #10203b; }
        .divider {
            width: 98px;
            height: 3px;
            background: #c49a56;
            margin: 0 0 24px;
        }
        .paragraph { font-size: 16px; line-height: 1.68; margin: 0 0 15px; max-width: 660px; color: #20314e; }
        .summary-table { width: 100%; border-collapse: separate; border-spacing: 0; margin: 8px 0 16px; table-layout: fixed; }
        .summary-table td {
            vertical-align: top;
            padding: 14px 16px 14px 0;
            width: 33.33%;
            border-top: 1px solid #e2d6c1;
            border-bottom: 1px solid #e2d6c1;
            background: linear-gradient(180deg, #fffdfa 0%, #fbf5ea 100%);
        }
        .summary-table td:first-child {
            padding-left: 14px;
            border-left: 1px solid #e2d6c1;
        }
        .summary-table td:last-child {
            border-right: 1px solid #e2d6c1;
        }
        .label { font-size: 13px; font-weight: 700; margin: 0 0 6px; text-transform: uppercase; letter-spacing: 0.08em; color: #7f5c28; }
        .amount { font-size: 24px; font-weight: 700; margin: 0; color: #10203b; }
        .balance-copy {
            font-size: 18px;
            font-weight: 700;
            line-height: 1.6;
            margin: 0 0 16px;
            max-width: 660px;
            padding: 13px 16px;
            border-left: 4px solid #c49a56;
            background: #f7f1e7;
            color: #10203b;
        }
        .schedule {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 10px;
            margin-top: 2px;
            table-layout: fixed;
        }
        .schedule td {
            vertical-align: top;
            padding: 12px 14px;
            background: #fffdfa;
            border: 1px solid #eadfce;
        }
        .schedule-left { width: 46%; border-right: none; }
        .schedule-right { width: 54%; border-left: none; }
        .installment-label { font-size: 16px; font-weight: 700; margin: 0 0 3px; color: #10203b; }
        .installment-amount { font-size: 18px; font-weight: 700; margin: 0; color: #10203b; }
        .installment-status { font-size: 14px; font-weight: 700; margin: 0 0 2px; text-transform: uppercase; letter-spacing: 0.08em; color: #8d6b36; }
        .installment-date { font-size: 15px; line-height: 1.5; margin: 0; color: #33415c; }
        .progress-note {
            margin: 0 0 14px;
            font-size: 13px;
            line-height: 1.6;
            color: #7a6850;
        }
        .status-paid { color: #2f6b45; }
        .status-scheduled { color: #8d6b36; }
        .footer {
            font-size: 15px;
            line-height: 1.75;
            margin-top: 22px;
            max-width: 660px;
            color: #33415c;
            border-top: 1px solid #e5d8c6;
            padding-top: 16px;
        }
        .signature {
            margin-top: 22px;
            width: 210px;
            border-top: 1px solid #bda47e;
            padding-top: 7px;
            font-size: 12px;
            color: #7c6b52;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="watermark">YOGAFX</div>
        <div class="sheet">
            <div class="top-band"></div>
            <p class="academy">YogaFX International Yoga Teacher Training Academy</p>
            <div class="meta-row">
                <div class="meta-left">
                    <h1 class="title">{$courseName}</h1>
                    <div class="subtitle">Confirmation</div>
                </div>
                <div class="meta-right">Installment<span>✓</span></div>
            </div>
            <div class="divider"></div>
            <p class="paragraph">Dear {$dearName},</p>
            <p class="paragraph">We are thrilled that you will be joining us for our {$courseName}.</p>
            <table class="summary-table">
                <tr>
                    <td><p class="label">Course Investment</p><p class="amount">{$coursePrice}</p></td>
                    <td><p class="label">1st Installment</p><p class="amount">{$firstInstallmentAmount}</p></td>
                    <td><p class="label">Balance</p><p class="amount">{$balanceDue}</p></td>
                </tr>
            </table>
            <p class="balance-copy">{$firstInstallmentAmount} Received on {$firstInstallmentReceivedOn}{$balanceCopy}</p>
            <p class="progress-note">Below is the current installment schedule recorded for this confirmation.</p>
            <table class="schedule">{$rowsHtml}</table>
            <p class="footer">Thank you for your interest in YogaFX International Yoga Teacher Training Academy<br>it really is appreciated</p>
            <div class="signature">YogaFX Admissions Team</div>
        </div>
    </div>
</body>
</html>
HTML;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
