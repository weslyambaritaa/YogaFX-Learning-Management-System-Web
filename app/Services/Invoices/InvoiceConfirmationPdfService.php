<?php

namespace App\Services\Invoices;

use App\Models\Invoice;
use App\Models\OnboardingState;
use App\Models\Payment;
use App\Models\PaymentSubscription;
use App\Models\User;
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
        $user = $invoice->user;
        $pendingRegistration = $invoice->pendingRegistration;
        $studentName = $this->studentName($invoice);
        $studentEmail = $user?->email ?: ($pendingRegistration?->email ?: '-');
        $accessTier = $invoice->accessTier ?? $user?->accessTier ?? $pendingRegistration?->accessTier;
        $latestSuccessfulPayment = $successPayments->last();

        $view = $invoice->payment_type === Invoice::PAYMENT_TYPE_INSTALLMENT
            ? 'pdf.installment-confirmation'
            : 'pdf.online-confirmation';

        $attachment = [
            'name' => $this->fileName($invoice, $studentName),
            'mime' => 'application/pdf',
            'data' => '',
        ];

        $html = view($view, [
            'invoice' => $invoice,
            'studentName' => $studentName,
            'studentEmail' => $studentEmail,
            'studentCountry' => $user?->country ?: ($pendingRegistration?->country ?: '-'),
            'packageTitle' => $invoice->package?->title ?: ($accessTier?->name ?: 'YogaFX Program'),
            'tierName' => $accessTier?->name ?: '-',
            'invoiceTypeLabel' => $invoice->type === Invoice::TYPE_UPGRADE ? 'Upgrade' : 'Initial Checkout',
            'paymentTypeLabel' => $invoice->payment_type === Invoice::PAYMENT_TYPE_INSTALLMENT ? 'Installment' : 'Pay Full',
            'currencyCode' => strtoupper((string) $invoice->currency_code),
            'totalAmount' => $this->formatMoney($invoice->total_amount, $invoice->currency_code),
            'balanceDue' => $this->formatMoney($invoice->balance_due, $invoice->currency_code),
            'totalPaid' => $this->formatMoney($successPayments->sum(fn (Payment $payment) => (float) $payment->amount_paid), $invoice->currency_code),
            'issuedAt' => optional($invoice->issued_at)->format('M j, Y') ?: '-',
            'paidAt' => optional($invoice->paid_at)->format('M j, Y') ?: '-',
            'generatedAt' => now()->format('M j, Y'),
            'statusLabel' => Str::of($invoice->status)->replace('_', ' ')->title()->toString(),
            'primaryPaymentReference' => $latestSuccessfulPayment?->payment_reference ?: '-',
            'paymentReferences' => $this->paymentReferences($successPayments),
            'installment' => $this->installmentData($invoice, $paymentSubscription),
            'supportEmail' => config('mail.from.address', config('app.name', 'YogaFX LMS')),
        ])->render();

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $attachment['data'] = $dompdf->output();

        return $attachment;
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
        $safeName = Str::of($studentName)
            ->lower()
            ->slug('-')
            ->value();

        $prefix = $invoice->payment_type === Invoice::PAYMENT_TYPE_INSTALLMENT
            ? 'installment-confirmation'
            : 'online-confirmation';

        return sprintf('%s-%s.pdf', $prefix, $safeName !== '' ? $safeName : 'student');
    }

    /**
     * @param  Collection<int, Payment>  $payments
     * @return array<int, array{reference: string, amount: string, paid_at: string}>
     */
    private function paymentReferences(Collection $payments): array
    {
        return $payments
            ->map(fn (Payment $payment) => [
                'reference' => $payment->payment_reference ?: '-',
                'amount' => $this->formatMoney($payment->amount_paid, $payment->currency_code),
                'paid_at' => optional($payment->updated_at)->format('M j, Y') ?: '-',
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{
     *     first_payment_amount: string,
     *     recurring_amount: string,
     *     billing_day: string,
     *     next_due_date: string,
     *     installment_count: string,
     *     installments_paid_count: string,
     *     final_due_date: string
     * }
     */
    private function installmentData(Invoice $invoice, ?PaymentSubscription $subscription): array
    {
        return [
            'first_payment_amount' => $subscription
                ? $this->formatMoney($subscription->first_payment_amount, $subscription->currency_code)
                : '-',
            'recurring_amount' => $subscription
                ? $this->formatMoney($subscription->next_billing_amount, $subscription->currency_code)
                : '-',
            'billing_day' => $subscription?->billing_day ? (string) $subscription->billing_day : '-',
            'next_due_date' => optional($subscription?->next_due_at)->format('M j, Y') ?: '-',
            'installment_count' => $subscription?->installment_count ? (string) $subscription->installment_count : '-',
            'installments_paid_count' => $subscription?->installments_paid_count !== null
                ? (string) $subscription->installments_paid_count
                : '-',
            'final_due_date' => optional($subscription?->final_due_at)->format('M j, Y') ?: '-',
        ];
    }

    private function formatMoney(float|int|string|null $amount, ?string $currencyCode): string
    {
        $numeric = (float) ($amount ?? 0);
        $currency = strtoupper((string) ($currencyCode ?: 'USD'));

        return sprintf('%s %s', $currency, number_format($numeric, 2, '.', ','));
    }
}
