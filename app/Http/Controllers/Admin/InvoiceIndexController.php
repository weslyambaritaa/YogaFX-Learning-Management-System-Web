<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccessTier;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceIndexController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Admin/Invoices/Index', [
            'sections' => [
                'initial_checkout' => $this->buildSection(
                    request: $request,
                    key: 'initial_checkout',
                    title: 'Initial Checkout Invoices',
                    description: 'One-time invoices created from the initial checkout flow.',
                    scope: fn (Builder $query) => $query
                        ->where('type', Invoice::TYPE_INITIAL)
                        ->where('payment_type', Invoice::PAYMENT_TYPE_FULL),
                ),
                'upgrade' => $this->buildSection(
                    request: $request,
                    key: 'upgrade',
                    title: 'Upgrade Invoices',
                    description: 'Invoices generated when a student upgrades into a higher tier.',
                    scope: fn (Builder $query) => $query
                        ->where('type', Invoice::TYPE_UPGRADE),
                ),
                'installment' => $this->buildSection(
                    request: $request,
                    key: 'installment',
                    title: 'Installment Invoices',
                    description: 'Invoices that are paid in installments from the initial checkout flow.',
                    scope: fn (Builder $query) => $query
                        ->where('type', Invoice::TYPE_INITIAL)
                        ->where('payment_type', Invoice::PAYMENT_TYPE_INSTALLMENT),
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
                ['value' => Invoice::STATUS_UNPAID, 'label' => 'Unpaid'],
                ['value' => Invoice::STATUS_PAID_FULL, 'label' => 'Paid Full'],
                ['value' => Invoice::STATUS_INSTALLMENT, 'label' => 'Installment'],
                ['value' => Invoice::STATUS_UPGRADED, 'label' => 'Upgraded'],
            ],
        ]);
    }

    public function destroy(Invoice $invoice): RedirectResponse
    {
        $invoice->delete();

        return redirect()
            ->back()
            ->with('status', 'invoice-deleted');
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

        $query = Invoice::query()
            ->with([
                'user:id,name,email,first_name,last_name',
                'pendingRegistration:id,first_name,last_name,email',
                'accessTier:id,name',
                'package:id,title',
            ]);

        $scope($query);

        $query
            ->when($search !== '', function (Builder $builder) use ($search) {
                $builder->where(function (Builder $nested) use ($search) {
                    $nested
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
            })
            ->when($status !== '', fn (Builder $builder) => $builder->where('status', $status))
            ->when($tierId !== null, fn (Builder $builder) => $builder->where('access_tier_id', $tierId))
            ->orderByDesc('issued_at')
            ->orderByDesc('id');

        $paginator = $query
            ->paginate($perPage, ['*'], $pageName, $page)
            ->withQueryString();

        $start = $paginator->firstItem() ?? 1;

        $paginator->setCollection(
            $paginator->getCollection()->values()->map(
                fn (Invoice $invoice, int $index) => $this->mapInvoiceRow($invoice, $start + $index),
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

    private function mapInvoiceRow(Invoice $invoice, int $number): array
    {
        $studentName = $invoice->user?->name
            ?: trim(implode(' ', array_filter([
                $invoice->pendingRegistration?->first_name,
                $invoice->pendingRegistration?->last_name,
            ])));

        $studentEmail = $invoice->user?->email
            ?: $invoice->pendingRegistration?->email;

        return [
            'id' => $invoice->id,
            'number' => $number,
            'invoice_number' => $invoice->invoice_number,
            'pdf_preview_url' => route('admin.invoices.pdf.preview', $invoice),
            'pdf_download_url' => route('admin.invoices.pdf.download', $invoice),
            'student_name' => $studentName !== '' ? $studentName : 'Unknown student',
            'email' => $studentEmail ?: '-',
            'tier' => $invoice->accessTier?->name ?? '-',
            'package' => $invoice->package?->title ?? '-',
            'type' => $invoice->type,
            'type_label' => $invoice->type === Invoice::TYPE_UPGRADE ? 'Upgrade' : 'Initial Checkout',
            'payment_type' => $invoice->payment_type,
            'payment_type_label' => $invoice->payment_type === Invoice::PAYMENT_TYPE_INSTALLMENT ? 'Installment' : 'Pay Full',
            'total_amount' => (float) $invoice->total_amount,
            'balance_due' => (float) $invoice->balance_due,
            'currency_code' => $invoice->currency_code,
            'status' => $invoice->status,
            'status_label' => str($invoice->status)->replace('_', ' ')->title()->toString(),
            'issued_at' => optional($invoice->issued_at)->toDateTimeString(),
            'paid_at' => optional($invoice->paid_at)->toDateTimeString(),
        ];
    }
}
