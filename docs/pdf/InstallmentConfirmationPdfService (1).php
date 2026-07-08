<?php

namespace App\Services;

use App\Models\PaymentPlan;
use App\Models\Payment;
use App\Models\Student;
use App\Support\Branding;
use App\Support\CurrencyDisplay;
use App\Support\InstallmentAllocation;
use App\Support\InvoicePaymentViewData;
use Carbon\CarbonImmutable;
use Dompdf\Dompdf;
use Dompdf\Options;

class InstallmentConfirmationPdfService
{
    /**
     * @return array{name:string,data:string,mime:string}
     */
    public function makeAttachment(Student $student, PaymentPlan $plan): array
    {
        $student->loadMissing('package');

        $invoice = $student->financialInvoice();
        $metadata = is_array($plan->metadata ?? null) ? $plan->metadata : [];
        $paymentView = InvoicePaymentViewData::make($invoice, $student, $plan);
        $currency = strtoupper((string) (
            $metadata['setup_fee_currency']
            ?? $paymentView['currency']
            ?? $student->financialCurrency()
        ));
        $installments = max(1, (int) ($plan->months ?? 1));
        $coursePrice = (float) (
            $metadata['course_price']
            ?? $paymentView['course_price']
            ?? $student->coursePriceAmount()
            ?? 0
        );
        if ($coursePrice <= 0) {
            $coursePrice = (float) ($student->package?->base_price ?? 0);
        }
        $balanceTransferredTotal = (float) ($paymentView['balance_transferred_total'] ?? 0);
        $ledgerDepositTransferred = (float) ($paymentView['deposit_transferred'] ?? 0);
        $baseTransferred = $balanceTransferredTotal > 0.009
            ? $balanceTransferredTotal + $ledgerDepositTransferred
            : $student->creditedOrInitialPaidAmount();
        $balanceDue = max(0.0, $coursePrice - $baseTransferred);
        $setupFeeCountsAsInstallment = (bool) ($metadata['setup_fee_counts_as_installment'] ?? false);
        $isPayIn4 = (string) ($plan->type ?? '') === 'pay_in_4';
        $isSetupFeeSchedule = $isPayIn4 || $setupFeeCountsAsInstallment;
        $monthly = (float) ($plan->monthly_amount ?? 0.0);
        if ($monthly <= 0) {
            $recurringInstallments = $isSetupFeeSchedule ? max(1, $installments - 1) : $installments;
            $monthly = $recurringInstallments > 0 ? ($balanceDue / $recurringInstallments) : $balanceDue;
        }
        if ($isSetupFeeSchedule) {
            $lastPayment = (float) ($plan->last_amount ?? 0);
            if ($lastPayment <= 0) {
                $setupFee = (float) ($metadata['setup_fee_amount'] ?? $monthly);
                $recurringBalance = max(0.0, $balanceDue - $setupFee);
                $lastPayment = round($recurringBalance - ($monthly * max(0, $installments - 2)), 2);
            }
        } else {
            $lastPayment = round($balanceDue - ($monthly * ($installments - 1)), 2);
        }
        if ($lastPayment <= 0) {
            $lastPayment = $monthly;
        }

        $firstInstallmentAmount = $installments === 1 ? $lastPayment : $monthly;
        $depositDisplayAmount = $baseTransferred;
        if ($setupFeeCountsAsInstallment) {
            $firstInstallmentAmount = (float) ($metadata['setup_fee_amount'] ?? $firstInstallmentAmount);
        }
        if ($isSetupFeeSchedule) {
            $depositDisplayAmount = $firstInstallmentAmount;
            $balanceDue = max(0.0, $balanceDue - $depositDisplayAmount);
        }

        $depositPaidAt = Payment::query()
            ->where('student_id', $student->id)
            ->where('status', 'paid')
            ->where('type', 'deposit_checkout')
            ->orderByDesc('paid_at')
            ->value('paid_at');

        $planTransactions = Payment::query()
            ->where('student_id', $student->id)
            ->where('payment_plan_id', $plan->id)
            ->orderByRaw('COALESCE(paid_at, created_at) asc')
            ->get(['status', 'amount', 'paid_at', 'created_at'])
            ->map(fn (Payment $p) => [
                'status' => (string) ($p->status ?? ''),
                'amount' => (float) ($p->amount ?? 0),
                'paid_at' => $p->paid_at?->toIso8601String(),
                'created_at' => $p->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        $installmentRows = $this->buildInstallmentRows(
            $plan,
            $currency,
            $installments,
            $monthly,
            $lastPayment,
            $planTransactions,
            $isSetupFeeSchedule,
            $firstInstallmentAmount
        );

        $hasFailed = collect($planTransactions)->contains(
            fn (array $tx) => strtolower((string) ($tx['status'] ?? '')) === 'failed'
        );
        $firstInstallmentPaidAt = $installmentRows[0]['received_on'] ?? null;
        $firstInstallmentStatus = strtolower((string) ($installmentRows[0]['status'] ?? 'scheduled'));
        $firstInstallmentFailed = $isSetupFeeSchedule && $hasFailed && $firstInstallmentStatus !== 'paid';

        if ($firstInstallmentFailed) {
            $depositDisplayAmount = 0.0;
            $firstInstallmentStatus = 'pending';
        }

        $title = trim((string) ($student->title ?? ''));
        $fullName = trim((string) $student->full_name);
        $dearName = trim(($title !== '' ? $title . ' ' : '') . $fullName);

        $deliveryMode = (string) ($student->package?->delivery_mode ?? 'hybrid');
        $paymentReceivedAt = Payment::query()
            ->where('student_id', $student->id)
            ->where('status', 'paid')
            ->where(function ($q) {
                $q->whereIn('type', ['deposit_checkout', 'remaining_balance_checkout', 'installment'])
                    ->orWhereNotNull('payment_plan_id');
            })
            ->orderByDesc('paid_at')
            ->value('paid_at');

        $isOnlineLike = in_array($deliveryMode, ['online', 'starter_kit'], true);
        $view = $isOnlineLike
            ? 'pdf.online-confirmation'
            : 'pdf.masterclass-installment-confirmation';

        $html = view($view, [
            'student' => $student,
            'plan' => $plan,
            'currency' => $currency,
            'installments' => $installments,
            'headerUrl' => Branding::pdfHeaderUrl(),
            'signatureUrl' => Branding::pdfSignatureUrl(),
            'watermarkUrl' => Branding::pdfWatermarkUrl(),
            'greenTickUrl' => Branding::greenTickUrl(),
            'isPayIn4' => $isPayIn4,
            'isSetupFeeSchedule' => $isSetupFeeSchedule,
            'paymentMode' => $isPayIn4 ? 'pay_in_4' : 'pay_in_full',
            'showCourseDates' => ! in_array((string) ($student->package?->delivery_mode ?? ''), ['online', 'starter_kit'], true),
            'dearName' => $dearName !== '' ? $dearName : 'Student',
            'courseName' => (string) ($student->package?->name ?: 'RYT 200 YTTC'),
            'coursePrice' => CurrencyDisplay::format($currency, $coursePrice, 0),
            'paymentReceivedOn' => $paymentReceivedAt
                ? CarbonImmutable::parse((string) $paymentReceivedAt)->format('M, jS Y')
                : now()->format('M, jS Y'),
            'courseDateRange' => $this->formatCourseDateRange($student),
            'checkInAt' => $student->package?->start_at?->format('l, M, jS Y') ?: '-',
            'checkOutAt' => $student->package?->end_at?->format('l, M, jS Y') ?: '-',
            'depositPaid' => CurrencyDisplay::format($currency, $depositDisplayAmount, 2),
            'depositLabel' => $isSetupFeeSchedule ? '1st Installment' : 'Deposit',
            'depositReceivedText' => $isSetupFeeSchedule ? 'Paid on' : 'Received on',
            'depositReceivedOn' => $depositPaidAt ? CarbonImmutable::parse((string) $depositPaidAt)->format('M, jS Y') : ($firstInstallmentPaidAt ?: now()->format('M, jS Y')),
            'hasDepositReceived' => (bool) $depositPaidAt || $firstInstallmentStatus === 'paid',
            'balanceDue' => CurrencyDisplay::format($currency, $balanceDue, 2),
            'installmentRows' => $installmentRows,
            'generatedOn' => now()->format('j M Y'),
            'showFirstInstallmentFailedNotice' => $firstInstallmentFailed,
            'payIn4DepositRow' => [
                'amount' => CurrencyDisplay::format($currency, $firstInstallmentFailed ? 0 : $depositDisplayAmount, 2),
                'received_on' => $depositPaidAt ? CarbonImmutable::parse((string) $depositPaidAt)->format('M, jS Y') : $firstInstallmentPaidAt,
                'status' => $firstInstallmentStatus,
            ],
        ])->render();

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $studentName = trim((string) $student->full_name);
        $safeName = preg_replace('/[^A-Za-z0-9]+/', '-', $studentName ?: 'student');
        $filenamePrefix = $isOnlineLike ? 'online-confirmation' : 'installment-confirmation';
        $filename = sprintf('%s-%s.pdf', $filenamePrefix, strtolower(trim((string) $safeName, '-')));

        return [
            'name' => $filename,
            'mime' => 'application/pdf',
            'data' => $dompdf->output(),
        ];
    }

    /**
     * @param  array<int, array{status?:string,amount?:float|int,paid_at?:string|null,created_at?:string|null}>  $transactions
     * @return array<int, array{label:string,amount:string,due_on:string,received_on:?string,status:string}>
     */
    private function buildInstallmentRows(
        PaymentPlan $plan,
        string $currency,
        int $installments,
        float $monthly,
        float $last,
        array $transactions = [],
        bool $skipFirst = false,
        float $firstInstallmentAmount = 0.0
    ): array
    {
        $tz = (string) ($plan->billing_timezone ?: 'UTC');
        $billingDay = (int) ($plan->billing_day ?: 1);
        if (! in_array($billingDay, [1, 15], true)) {
            $billingDay = 1;
        }

        $base = $plan->created_at
            ? CarbonImmutable::parse((string) $plan->created_at, $tz)
            : CarbonImmutable::now($tz);
        $first = $base->day <= $billingDay
            ? $base->startOfMonth()->setDay($billingDay)
            : $base->addMonthNoOverflow()->startOfMonth()->setDay($billingDay);

        $scheduledAmounts = [];
        for ($i = 1; $i <= $installments; $i++) {
            if ($skipFirst && $i === 1) {
                $scheduledAmounts[] = $firstInstallmentAmount > 0 ? $firstInstallmentAmount : $monthly;
            } else {
                $scheduledAmounts[] = $i === $installments ? $last : $monthly;
            }
        }
        $allocation = InstallmentAllocation::allocate($scheduledAmounts, $transactions);
        $firstUnpaid = $allocation['first_unpaid_index'] ?? null;

        $rows = [];
        for ($i = 1; $i <= $installments; $i++) {
            if ($skipFirst && $i === 1) {
                $amount = $firstInstallmentAmount > 0 ? $firstInstallmentAmount : $monthly;
            } else {
                $amount = $i === $installments ? $last : $monthly;
            }
            if ($skipFirst && $i === 1) {
                $due = $base;
            } else {
                // If first installment is setup fee, the next cycle starts exactly
                // on selected billing day (no extra one-month shift).
                $dueOffset = $skipFirst ? ($i - 2) : ($i - 1);
                $due = $first->addMonthsNoOverflow(max(0, $dueOffset));
            }
            $slot = $allocation['slots'][$i - 1] ?? ['paid' => false, 'paid_at' => null];
            $status = ($slot['paid'] ?? false) ? 'paid' : (
                (($allocation['has_failed'] ?? false) && $firstUnpaid !== null && $firstUnpaid === ($i - 1))
                    ? 'failed'
                    : 'scheduled'
            );

            $rows[] = [
                'label' => sprintf('%s Installment', $this->ordinal($i)),
                'amount' => CurrencyDisplay::format($currency, $amount, 2),
                'due_on' => $due->format('M, jS Y'),
                'received_on' => (string) ($slot['paid_at'] ?? '') !== '' ? (string) $slot['paid_at'] : null,
                'status' => $status,
            ];
        }

        return $rows;
    }

    private function ordinal(int $n): string
    {
        if ($n % 100 >= 11 && $n % 100 <= 13) {
            return $n . 'th';
        }

        return match ($n % 10) {
            1 => $n . 'st',
            2 => $n . 'nd',
            3 => $n . 'rd',
            default => $n . 'th',
        };
    }

    private function formatCourseDateRange(Student $student): string
    {
        $start = $student->package?->start_at;
        $end = $student->package?->end_at;

        if (! $start || ! $end) {
            return '-';
        }

        return sprintf('%s to %s', $start->format('l, jS F Y'), $end->format('l, jS F Y'));
    }
}
