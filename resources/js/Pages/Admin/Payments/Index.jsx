import CommerceIndexPage, {
    formatCurrency,
    formatDateTime,
    statusBadgeClass,
} from '@/Components/admin/commerce/CommerceIndexPage';
import DeleteConfirmationDialog from '@/Components/DeleteConfirmationDialog';
import { Button } from '@/Components/ui/button';

const columns = [
    {
        key: 'number',
        label: 'No',
        render: (row) => row.number,
    },
    {
        key: 'payment_reference',
        label: 'Payment Reference',
        render: (row) => (
            <div>
                <div className="font-medium text-slate-900">{row.payment_reference}</div>
                <div className="text-xs text-slate-500">{row.payment_type_label}</div>
            </div>
        ),
    },
    {
        key: 'invoice_number',
        label: 'Invoice',
        render: (row) => (
            <div>
                <div className="font-medium text-slate-900">{row.invoice_number}</div>
                <div className="text-xs text-slate-500">{row.invoice_type_label}</div>
            </div>
        ),
    },
    {
        key: 'student',
        label: 'Student',
        render: (row) => (
            <div>
                <div className="font-medium text-slate-900">{row.student_name}</div>
                <div className="text-xs text-slate-500">{row.email}</div>
            </div>
        ),
    },
    {
        key: 'amount_paid',
        label: 'Amount Paid',
        render: (row) => formatCurrency(row.amount_paid, row.currency_code),
    },
    {
        key: 'payment_method',
        label: 'Method',
        render: (row) => row.payment_method_label,
    },
    {
        key: 'status',
        label: 'Status',
        render: (row) => (
            <span className={`inline-flex rounded-full px-3 py-1 text-xs font-semibold ${statusBadgeClass(row.status)}`}>
                {row.status_label}
            </span>
        ),
    },
    {
        key: 'created_at',
        label: 'Created At',
        render: (row) => formatDateTime(row.created_at),
    },
    {
        key: 'action',
        label: 'Action',
        render: (row, openDetail) => (
            <div className="flex flex-wrap items-center gap-2">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() => openDetail(row)}
                >
                    Detail
                </Button>

                <DeleteConfirmationDialog
                    href={route('admin.payments.destroy', row.id)}
                    title="Delete payment?"
                    description={`This will permanently delete payment "${row.payment_reference}". This action cannot be undone.`}
                />
            </div>
        ),
    },
];

export default function PaymentsIndex({ sections, tierOptions, statusOptions }) {
    return (
        <CommerceIndexPage
            title="Payment"
            heading="Payment"
            description="Monitor payment activity across one-time checkout, installments, and upgrade flows from one admin surface."
            indexRoute="admin.payments.index"
            sections={sections}
            tierOptions={tierOptions}
            statusOptions={statusOptions}
            columns={columns}
            detailTitle={(row) => `Payment ${row.payment_reference}`}
            detailDescription={(row) => `Full payment detail for ${row.student_name}.`}
            buildDetailFields={(row) => [
                { label: 'Payment Reference', value: row.payment_reference },
                { label: 'Invoice Number', value: row.invoice_number },
                { label: 'Student Name', value: row.student_name },
                { label: 'Email', value: row.email },
                { label: 'Tier', value: row.tier },
                { label: 'Package', value: row.package },
                { label: 'Invoice Type', value: row.invoice_type_label },
                { label: 'Payment Method', value: row.payment_method_label },
                { label: 'Payment Type', value: row.payment_type_label },
                { label: 'Amount Paid', value: formatCurrency(row.amount_paid, row.currency_code) },
                { label: 'Currency', value: row.currency_code },
                { label: 'Status', value: row.status_label },
                { label: 'Created At', value: formatDateTime(row.created_at) },
                { label: 'Notes', value: row.notes },
            ]}
        />
    );
}
