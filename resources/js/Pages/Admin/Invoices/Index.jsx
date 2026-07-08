import CommerceIndexPage, {
    formatCurrency,
    formatDateTime,
    statusBadgeClass,
} from "@/Components/admin/commerce/CommerceIndexPage";
import { Button } from "@/Components/ui/button";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from "@/Components/ui/dropdown-menu";
import { ChevronDown } from "lucide-react";

const columns = [
    {
        key: "number",
        label: "No",
        render: (row) => row.number,
    },
    {
        key: "invoice_number",
        label: "Invoice",
        render: (row) => (
            <div>
                <div className="font-medium text-slate-900">
                    {row.invoice_number}
                </div>
                <div className="text-xs text-slate-500">{row.type_label}</div>
            </div>
        ),
    },
    {
        key: "student",
        label: "Student",
        render: (row) => (
            <div>
                <div className="font-medium text-slate-900">
                    {row.student_name}
                </div>
                <div className="text-xs text-slate-500">{row.email}</div>
            </div>
        ),
    },
    {
        key: "tier",
        label: "Tier",
        render: (row) => row.tier,
    },
    {
        key: "total_amount",
        label: "Total Amount",
        render: (row) => formatCurrency(row.total_amount, row.currency_code),
    },
    {
        key: "balance_due",
        label: "Balance Due",
        render: (row) => formatCurrency(row.balance_due, row.currency_code),
    },
    {
        key: "status",
        label: "Status",
        render: (row) => (
            <span
                className={`inline-flex rounded-full px-3 py-1 text-xs font-semibold ${statusBadgeClass(row.status)}`}
            >
                {row.status_label}
            </span>
        ),
    },
    {
        key: "issued_at",
        label: "Issued At",
        render: (row) => formatDateTime(row.issued_at),
    },
    {
        key: "action",
        label: "Action",
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

                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button type="button" size="sm" className="gap-1.5">
                            PDF
                            <ChevronDown className="size-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        align="end"
                        className="z-50 w-40 min-w-40 rounded-md border border-slate-200 bg-white p-1 shadow-lg"
                    >
                        <DropdownMenuItem asChild>
                            <a
                                href={row.pdf_preview_url}
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                Preview PDF
                            </a>
                        </DropdownMenuItem>
                        <DropdownMenuItem asChild>
                            <a href={row.pdf_download_url}>Download PDF</a>
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        ),
    },
];

export default function InvoicesIndex({
    sections,
    tierOptions,
    statusOptions,
}) {
    return (
        <CommerceIndexPage
            title="Invoice"
            heading="Invoice"
            description="Review invoice activity by checkout context without expanding this into a full finance backoffice."
            indexRoute="admin.invoices.index"
            sections={sections}
            tierOptions={tierOptions}
            statusOptions={statusOptions}
            columns={columns}
            detailTitle={(row) => `Invoice ${row.invoice_number}`}
            detailDescription={(row) =>
                `Full invoice detail for ${row.student_name}.`
            }
            buildDetailFields={(row) => [
                { label: "Invoice Number", value: row.invoice_number },
                { label: "Student Name", value: row.student_name },
                { label: "Email", value: row.email },
                { label: "Tier", value: row.tier },
                { label: "Package", value: row.package },
                { label: "Type", value: row.type_label },
                { label: "Payment Type", value: row.payment_type_label },
                {
                    label: "Total Amount",
                    value: formatCurrency(row.total_amount, row.currency_code),
                },
                {
                    label: "Balance Due",
                    value: formatCurrency(row.balance_due, row.currency_code),
                },
                { label: "Currency", value: row.currency_code },
                { label: "Status", value: row.status_label },
                { label: "Issued At", value: formatDateTime(row.issued_at) },
                { label: "Paid At", value: formatDateTime(row.paid_at) },
            ]}
        />
    );
}
