<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccessTier;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PaymentIndexController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Admin/Payments/Index', [
            'sections' => [
                'pay_full' => $this->buildSection(
                    request: $request,
                    key: 'pay_full',
                    title: 'Pay Full Payments',
                    description: 'Successful and pending one-time payments from the initial checkout flow.',
                    scope: fn (Builder $query) => $query->whereHas('invoice', function (Builder $invoiceQuery) {
                        $invoiceQuery
                            ->where('type', Invoice::TYPE_INITIAL)
                            ->where('payment_type', Invoice::PAYMENT_TYPE_FULL);
                    }),
                ),
                'installment_first' => $this->buildSection(
                    request: $request,
                    key: 'installment_first',
                    title: 'Installment First Payments',
                    description: 'The first collected payment for initial checkout installment invoices.',
                    scope: fn (Builder $query) => $query
                        ->where('payment_type', Payment::TYPE_INSTALLMENT)
                        ->whereHas('invoice', fn (Builder $invoiceQuery) => $invoiceQuery
                            ->where('type', Invoice::TYPE_INITIAL)
                            ->where('payment_type', Invoice::PAYMENT_TYPE_INSTALLMENT))
                        ->whereIn('payment_activities.id', $this->firstInitialInstallmentPaymentIdsQuery()),
                ),
                'installment_recurring' => $this->buildSection(
                    request: $request,
                    key: 'installment_recurring',
                    title: 'Recurring Installment Payments',
                    description: 'Recurring payments collected after the first installment payment.',
                    scope: fn (Builder $query) => $query
                        ->where('payment_type', Payment::TYPE_INSTALLMENT)
                        ->whereHas('invoice', fn (Builder $invoiceQuery) => $invoiceQuery
                            ->where('type', Invoice::TYPE_INITIAL)
                            ->where('payment_type', Invoice::PAYMENT_TYPE_INSTALLMENT))
                        ->whereNotIn('payment_activities.id', $this->firstInitialInstallmentPaymentIdsQuery()),
                ),
                'upgrade' => $this->buildSection(
                    request: $request,
                    key: 'upgrade',
                    title: 'Upgrade Payments',
                    description: 'All payments associated with tier upgrade invoices.',
                    scope: fn (Builder $query) => $query->whereHas('invoice', fn (Builder $invoiceQuery) => $invoiceQuery
                        ->where('type', Invoice::TYPE_UPGRADE)),
                ),
            ],
            'tierOptions' => AccessTier::query()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (AccessTier $tier) => [
                    'value' => $tier->id,
                    'label' => $tier->name,
                ]),
            'statusOptions' => [
                ['value' => Payment::STATUS_PENDING, 'label' => 'Pending'],
                ['value' => Payment::STATUS_SUCCESS, 'label' => 'Success'],
                ['value' => Payment::STATUS_FAILED, 'label' => 'Failed'],
                ['value' => Payment::STATUS_CANCELLED, 'label' => 'Cancelled'],
            ],
        ]);
    }

    private function buildSection(
        Request $request,
        string $key,
        string $title,
        string $description,
        callable $scope,
    ): array {
        $search = trim((string) $request->input($key.'_search', ''));
        $status = trim((string) $request->input($key.'_status', ''));
        $tierId = $request->input($key.'_tier');
        $tierId = is_numeric($tierId) ? (int) $tierId : null;

        $perPage = (int) $request->integer($key.'_per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50], true) ? $perPage : 10;

        $pageName = $key.'_page';
        $page = max(1, (int) $request->integer($pageName, 1));

        $query = Payment::query()
            ->with([
                'invoice.user:id,name,email,first_name,last_name',
                'invoice.pendingRegistration:id,first_name,last_name,email',
                'invoice.accessTier:id,name',
                'invoice.package:id,title',
            ]);

        $scope($query);

        $query
            ->when($search !== '', function (Builder $builder) use ($search) {
                $builder->where(function (Builder $nested) use ($search) {
                    $nested
                        ->where('payment_reference', 'ilike', '%'.$search.'%')
                        ->orWhereHas('invoice', function (Builder $invoiceQuery) use ($search) {
                            $invoiceQuery
                                ->where('invoice_number', 'ilike', '%'.$search.'%')
                                ->orWhereHas('user', function (Builder $userQuery) use ($search) {
                                    $userQuery
                                        ->where('name', 'ilike', '%'.$search.'%')
                                        ->orWhere('email', 'ilike', '%'.$search.'%')
                                        ->orWhere('first_name', 'ilike', '%'.$search.'%')
                                        ->orWhere('last_name', 'ilike', '%'.$search.'%');
                                })
                                ->orWhereHas('pendingRegistration', function (Builder $registrationQuery) use ($search) {
                                    $registrationQuery
                                        ->where('email', 'ilike', '%'.$search.'%')
                                        ->orWhere('first_name', 'ilike', '%'.$search.'%')
                                        ->orWhere('last_name', 'ilike', '%'.$search.'%');
                                })
                                ->orWhereHas('accessTier', fn (Builder $tierQuery) => $tierQuery->where('name', 'ilike', '%'.$search.'%'))
                                ->orWhereHas('package', fn (Builder $packageQuery) => $packageQuery->where('title', 'ilike', '%'.$search.'%'));
                        });
                });
            })
            ->when($status !== '', fn (Builder $builder) => $builder->where('status', $status))
            ->when($tierId !== null, fn (Builder $builder) => $builder->whereHas('invoice', fn (Builder $invoiceQuery) => $invoiceQuery->where('access_tier_id', $tierId)))
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $paginator = $query
            ->paginate($perPage, ['*'], $pageName, $page)
            ->withQueryString();

        $start = $paginator->firstItem() ?? 1;

        $paginator->setCollection(
            $paginator->getCollection()->values()->map(
                fn (Payment $payment, int $index) => $this->mapPaymentRow($payment, $start + $index),
            ),
        );

        return [
            'key' => $key,
            'title' => $title,
            'description' => $description,
            'rows' => $paginator,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'tier' => $tierId,
                'per_page' => $perPage,
            ],
        ];
    }

    private function mapPaymentRow(Payment $payment, int $number): array
    {
        $invoice = $payment->invoice;
        $studentName = $invoice?->user?->name
            ?: trim(implode(' ', array_filter([
                $invoice?->pendingRegistration?->first_name,
                $invoice?->pendingRegistration?->last_name,
            ])));

        $studentEmail = $invoice?->user?->email
            ?: $invoice?->pendingRegistration?->email;

        return [
            'id' => $payment->id,
            'number' => $number,
            'payment_reference' => $payment->payment_reference ?: '-',
            'invoice_number' => $invoice?->invoice_number ?? '-',
            'student_name' => $studentName !== '' ? $studentName : 'Unknown student',
            'email' => $studentEmail ?: '-',
            'tier' => $invoice?->accessTier?->name ?? '-',
            'package' => $invoice?->package?->title ?? '-',
            'invoice_type' => $invoice?->type ?? null,
            'invoice_type_label' => ($invoice?->type ?? null) === Invoice::TYPE_UPGRADE ? 'Upgrade' : 'Initial Checkout',
            'payment_method' => $payment->payment_method,
            'payment_method_label' => str($payment->payment_method)->replace('_', ' ')->title()->toString(),
            'payment_type' => $payment->payment_type,
            'payment_type_label' => $payment->payment_type === Payment::TYPE_INSTALLMENT ? 'Installment' : 'Pay Full',
            'amount_paid' => (float) $payment->amount_paid,
            'currency_code' => $payment->currency_code,
            'status' => $payment->status,
            'status_label' => str($payment->status)->replace('_', ' ')->title()->toString(),
            'created_at' => optional($payment->created_at)->toDateTimeString(),
            'notes' => $payment->notes ?: '-',
        ];
    }

    private function firstInitialInstallmentPaymentIdsQuery()
    {
        return Payment::query()
            ->selectRaw('MIN(payment_activities.id)')
            ->join('invoices', 'invoices.id', '=', 'payment_activities.invoice_id')
            ->where('payment_activities.payment_type', Payment::TYPE_INSTALLMENT)
            ->where('invoices.type', Invoice::TYPE_INITIAL)
            ->where('invoices.payment_type', Invoice::PAYMENT_TYPE_INSTALLMENT)
            ->groupBy('payment_activities.invoice_id');
    }
}
