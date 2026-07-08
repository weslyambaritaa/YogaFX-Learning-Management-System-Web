<?php

namespace App\Services\Invoices;

use App\Models\AccessTier;
use App\Models\Invoice;
use App\Models\OnboardingState;
use App\Models\Payment;
use App\Models\PaymentSubscription;
use App\Models\User;
use App\Services\EmailBrandingService;
use Carbon\CarbonImmutable;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class InvoiceConfirmationPdfService
{
    private const GREEN_TICK_URL = 'https://yogafx-training.b-cdn.net/branding/green-tick-20260402083851-4a7f71fb.png';

    public function __construct(
        private readonly EmailBrandingService $emailBrandingService,
    ) {}

    /**
     * @return array{name: string, mime: string, data: string}
     */
    public function makeAttachment(Invoice $invoice): array
    {
        $invoice = $invoice->fresh([
            'user.accessTier',
            'pendingRegistration.accessTier',
            'accessTier',
            'package.accessTier',
            'payments' => fn ($query) => $query->orderBy('id'),
            'paymentSubscriptions' => fn ($query) => $query->orderByDesc('id'),
        ]) ?? $invoice;

        $paymentSubscription = $invoice->paymentSubscriptions->first();
        $payments = $invoice->payments->values();
        $successPayments = $payments
            ->filter(fn (Payment $payment) => $payment->status === Payment::STATUS_SUCCESS)
            ->values();

        $onlineLike = $this->isOnlineLike($invoice);
        $paymentMode = $invoice->payment_type === Invoice::PAYMENT_TYPE_INSTALLMENT
            ? 'pay_in_4'
            : 'pay_in_full';
        $studentName = $this->studentName($invoice);

        $html = view(
            $this->templateView($onlineLike),
            $this->viewData(
                invoice: $invoice,
                subscription: $paymentSubscription,
                payments: $payments,
                successPayments: $successPayments,
                studentName: $studentName,
                paymentMode: $paymentMode,
                onlineLike: $onlineLike,
            ),
        )->render();

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return [
            'name' => $this->fileName($invoice, $studentName, $onlineLike),
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

    private function templateView(bool $onlineLike): string
    {
        return $onlineLike
            ? 'pdf.online-confirmation'
            : 'pdf.masterclass-installment-confirmation';
    }

    /**
     * @param  Collection<int, Payment>  $payments
     * @param  Collection<int, Payment>  $successPayments
     * @return array<string, mixed>
     */
    private function viewData(
        Invoice $invoice,
        ?PaymentSubscription $subscription,
        Collection $payments,
        Collection $successPayments,
        string $studentName,
        string $paymentMode,
        bool $onlineLike,
    ): array {
        $courseName = $this->courseName($invoice);
        $coursePriceAmount = (float) $invoice->total_amount;
        $firstPaymentAmount = $this->firstPaymentAmount($invoice, $subscription, $successPayments);
        $balanceDueAmount = max(0, round((float) $invoice->balance_due, 2));
        $installmentRows = $this->buildInstallmentRows($invoice, $subscription, $successPayments);
        $depositReceivedOn = $this->depositReceivedOn($subscription, $successPayments);
        $showCourseDates = ! $onlineLike && $this->masterclassHasCourseDates($invoice);
        $branding = $this->emailBrandingService->currentPdfBrandingPayload();
        $tierSlug = $this->tierSlug($invoice);

        return [
            'pdfHeaderHtml' => $branding['pdf_header_html'] ?? '',
            'pdfFooterHtml' => $branding['pdf_footer_html'] ?? '',
            'watermarkHtml' => $branding['watermark_html'] ?? '',
            'greenTickUrl' => self::GREEN_TICK_URL,
            'generatedOn' => now()->format('j M Y'),
            'courseName' => $courseName,
            'courseHeading' => $this->courseHeading($courseName),
            'dearName' => $studentName !== '' ? $studentName : 'Student',
            'paymentMode' => $paymentMode,
            'isPayIn4' => $paymentMode === 'pay_in_4',
            'isSetupFeeSchedule' => $paymentMode === 'pay_in_4',
            'coursePrice' => $this->formatMoney($coursePriceAmount, $invoice->currency_code, $this->summaryDecimals($coursePriceAmount)),
            'paymentReceivedOn' => $this->paymentReceivedOn($successPayments),
            'depositPaid' => $this->formatMoney($firstPaymentAmount, $invoice->currency_code, $this->detailDecimals($firstPaymentAmount)),
            'depositLabel' => '1st Installment:',
            'depositReceivedText' => 'Received on',
            'depositReceivedOn' => $depositReceivedOn,
            'hasDepositReceived' => $successPayments->isNotEmpty(),
            'balanceDue' => $this->formatMoney($balanceDueAmount, $invoice->currency_code, $this->detailDecimals($balanceDueAmount)),
            'installmentRows' => $installmentRows,
            'showFirstInstallmentFailedNotice' => false,
            'payIn4DepositRow' => [
                'amount' => $this->formatMoney($firstPaymentAmount, $invoice->currency_code, $this->detailDecimals($firstPaymentAmount)),
                'received_on' => $depositReceivedOn,
                'status' => $successPayments->isNotEmpty() ? 'paid' : 'scheduled',
            ],
            'showCourseDates' => $showCourseDates,
            'courseDateRange' => $this->masterclassCourseDateRange($invoice),
            'checkInAt' => $this->masterclassCheckIn($invoice),
            'checkOutAt' => $this->masterclassCheckOut($invoice),
            'showBonuses' => $tierSlug !== AccessTier::SLUG_STARTER_KIT,
            'isInstallmentFullyPaid' => $paymentMode === 'pay_in_4'
                && $installmentRows !== []
                && collect($installmentRows)->every(fn (array $row) => strtolower((string) ($row['status'] ?? '')) === 'paid'),
        ];
    }

    private function isOnlineLike(Invoice $invoice): bool
    {
        $slug = AccessTier::canonicalSlug((string) (
            $invoice->accessTier?->slug
            ?: $invoice->package?->accessTier?->slug
            ?: $invoice->pendingRegistration?->accessTier?->slug
            ?: $invoice->user?->accessTier?->slug
            ?: ''
        ));

        return in_array($slug, [AccessTier::SLUG_ONLINE, AccessTier::SLUG_STARTER_KIT], true);
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

    private function courseHeading(string $courseName): string
    {
        $heading = trim($courseName);

        if ($heading === '') {
            return 'YogaFX Program';
        }

        if (! str_starts_with(Str::lower($heading), 'yogafx')) {
            $heading = 'YogaFX '.$heading;
        }

        return str_replace('OnlineClass', 'Online', $heading);
    }

    private function tierSlug(Invoice $invoice): string
    {
        return AccessTier::canonicalSlug((string) (
            $invoice->accessTier?->slug
            ?: $invoice->package?->accessTier?->slug
            ?: $invoice->pendingRegistration?->accessTier?->slug
            ?: $invoice->user?->accessTier?->slug
            ?: ''
        ));
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

    private function fileName(Invoice $invoice, string $studentName, bool $onlineLike): string
    {
        $safeName = Str::of($studentName)->lower()->slug('-')->value();
        $prefix = $onlineLike ? 'online-confirmation' : 'installment-confirmation';

        return sprintf('%s-%s.pdf', $prefix, $safeName !== '' ? $safeName : 'student');
    }

    /**
     * @param  Collection<int, Payment>  $successPayments
     * @return array<int, array{
     *     label: string,
     *     amount: string,
     *     due_on: string,
     *     received_on: ?string,
     *     status: string
     * }>
     */
    private function buildInstallmentRows(
        Invoice $invoice,
        ?PaymentSubscription $subscription,
        Collection $successPayments,
    ): array {
        if ($invoice->payment_type !== Invoice::PAYMENT_TYPE_INSTALLMENT) {
            return [];
        }

        $scheduleBreakdown = $this->scheduleBreakdown($invoice, $subscription, $successPayments);
        $successfulPayments = $successPayments->values();
        $rows = [];

        foreach ($scheduleBreakdown as $index => $scheduleRow) {
            $payment = $successfulPayments->get($index);
            $isPaid = $payment instanceof Payment;
            $dateSource = $isPaid
                ? optional($payment->updated_at ?? $payment->created_at)->toDateString()
                : (string) $scheduleRow['due_at'];

            $rows[] = [
                'label' => sprintf('%s Installment', $this->ordinal((int) $scheduleRow['cycle_number'])),
                'amount' => $this->formatMoney(
                    (float) $scheduleRow['amount'],
                    $invoice->currency_code,
                    $this->detailDecimals((float) $scheduleRow['amount']),
                ),
                'due_on' => $this->humanDate((string) $scheduleRow['due_at']),
                'received_on' => $isPaid ? $this->humanDate((string) $dateSource) : null,
                'status' => $isPaid ? 'paid' : 'scheduled',
            ];
        }

        return $rows;
    }

    /**
     * @param  Collection<int, Payment>  $successPayments
     * @return array<int, array{cycle_number:int,amount:float,due_at:string}>
     */
    private function scheduleBreakdown(
        Invoice $invoice,
        ?PaymentSubscription $subscription,
        Collection $successPayments,
    ): array {
        $metadata = is_array($subscription?->metadata) ? $subscription->metadata : [];
        $installmentPlan = is_array($metadata['installment_plan'] ?? null) ? $metadata['installment_plan'] : [];
        $scheduleBreakdown = [];

        if (is_array($installmentPlan['schedule_breakdown'] ?? null)) {
            $scheduleBreakdown = array_values(array_filter(
                $installmentPlan['schedule_breakdown'],
                fn ($row) => is_array($row) && isset($row['cycle_number'], $row['amount'], $row['due_at']),
            ));
        }

        if ($scheduleBreakdown !== []) {
            return array_map(fn (array $row) => [
                'cycle_number' => (int) $row['cycle_number'],
                'amount' => (float) $row['amount'],
                'due_at' => (string) $row['due_at'],
            ], $scheduleBreakdown);
        }

        if (! $subscription instanceof PaymentSubscription) {
            $paidAt = optional($successPayments->first()?->updated_at ?? $invoice->issued_at)->toDateString() ?? now()->toDateString();

            return [[
                'cycle_number' => 1,
                'amount' => (float) ($successPayments->first()?->amount_paid ?? $invoice->total_amount),
                'due_at' => $paidAt,
            ]];
        }

        $installmentCount = max(1, (int) $subscription->installment_count);
        $breakdown = [[
            'cycle_number' => 1,
            'amount' => (float) ($subscription->first_payment_amount ?: $successPayments->first()?->amount_paid ?: 0),
            'due_at' => optional($subscription->first_payment_paid_at ?? $subscription->started_at ?? $invoice->issued_at)->toDateString()
                ?? now()->toDateString(),
        ]];

        for ($cycle = 2; $cycle <= $installmentCount; $cycle++) {
            $amount = (float) ($subscription->next_billing_amount ?: $subscription->monthly_base_amount ?: 0);
            $breakdown[] = [
                'cycle_number' => $cycle,
                'amount' => $amount,
                'due_at' => $this->fallbackRecurringDate($subscription, $cycle)->toDateString(),
            ];
        }

        return $breakdown;
    }

    private function firstPaymentAmount(
        Invoice $invoice,
        ?PaymentSubscription $subscription,
        Collection $successPayments,
    ): float {
        return (float) (
            $subscription?->first_payment_amount
            ?? $successPayments->first()?->amount_paid
            ?? $invoice->total_amount
        );
    }

    /**
     * @param  Collection<int, Payment>  $successPayments
     */
    private function depositReceivedOn(?PaymentSubscription $subscription, Collection $successPayments): string
    {
        $date = optional(
            $subscription?->first_payment_paid_at
            ?? $successPayments->first()?->updated_at
            ?? $successPayments->first()?->created_at
            ?? now()
        )->toDateString() ?? now()->toDateString();

        return $this->humanDate($date);
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

    private function masterclassHasCourseDates(Invoice $invoice): bool
    {
        return $this->masterclassCourseDateRange($invoice) !== '-';
    }

    private function masterclassCourseDateRange(Invoice $invoice): string
    {
        $metadata = is_array($invoice->package?->metadata) ? $invoice->package->metadata : [];

        $value = $metadata['course_date_range']
            ?? $metadata['course_dates']
            ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : '-';
    }

    private function masterclassCheckIn(Invoice $invoice): string
    {
        $metadata = is_array($invoice->package?->metadata) ? $invoice->package->metadata : [];
        $value = $metadata['check_in_at'] ?? $metadata['check_in'] ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : '-';
    }

    private function masterclassCheckOut(Invoice $invoice): string
    {
        $metadata = is_array($invoice->package?->metadata) ? $invoice->package->metadata : [];
        $value = $metadata['check_out_at'] ?? $metadata['check_out'] ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : '-';
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
