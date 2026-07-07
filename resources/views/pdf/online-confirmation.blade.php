<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $invoice->invoice_number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #0f172a; margin: 0; background: #f8fafc; }
        .page { padding: 36px 34px 30px; }
        .card { background: #ffffff; border: 1px solid #dbe4ee; border-radius: 18px; padding: 28px; }
        .eyebrow { font-size: 11px; letter-spacing: 0.22em; text-transform: uppercase; color: #64748b; }
        .title { font-size: 28px; font-weight: 700; margin: 10px 0 6px; color: #0f172a; }
        .subtitle { font-size: 13px; color: #475569; margin: 0; line-height: 1.6; }
        .grid { width: 100%; margin-top: 22px; }
        .grid td { vertical-align: top; width: 50%; padding: 0 10px 14px 0; }
        .label { font-size: 10px; letter-spacing: 0.16em; text-transform: uppercase; color: #94a3b8; margin-bottom: 6px; }
        .value { font-size: 14px; font-weight: 600; color: #0f172a; }
        .section { margin-top: 26px; padding-top: 18px; border-top: 1px solid #e2e8f0; }
        .section-title { font-size: 14px; font-weight: 700; color: #0f172a; margin: 0 0 12px; }
        .reference-table { width: 100%; border-collapse: collapse; font-size: 12px; }
        .reference-table th, .reference-table td { border-bottom: 1px solid #e2e8f0; padding: 10px 0; text-align: left; }
        .reference-table th { color: #64748b; font-size: 11px; text-transform: uppercase; letter-spacing: 0.14em; }
        .footer { margin-top: 24px; font-size: 12px; color: #64748b; line-height: 1.7; }
        .accent { color: #0f172a; font-weight: 700; }
    </style>
</head>
<body>
    <div class="page">
        <div class="card">
            <div class="eyebrow">YogaFX LMS</div>
            <h1 class="title">Online Confirmation</h1>
            <p class="subtitle">
                This confirmation summarizes the invoice, payment status, and package access associated with this YogaFX purchase.
            </p>

            <table class="grid" cellspacing="0" cellpadding="0">
                <tr>
                    <td>
                        <div class="label">Student Name</div>
                        <div class="value">{{ $studentName }}</div>
                    </td>
                    <td>
                        <div class="label">Email</div>
                        <div class="value">{{ $studentEmail }}</div>
                    </td>
                </tr>
                <tr>
                    <td>
                        <div class="label">Package</div>
                        <div class="value">{{ $packageTitle }}</div>
                    </td>
                    <td>
                        <div class="label">Tier</div>
                        <div class="value">{{ $tierName }}</div>
                    </td>
                </tr>
                <tr>
                    <td>
                        <div class="label">Invoice Number</div>
                        <div class="value">{{ $invoice->invoice_number }}</div>
                    </td>
                    <td>
                        <div class="label">Primary Payment Reference</div>
                        <div class="value">{{ $primaryPaymentReference }}</div>
                    </td>
                </tr>
                <tr>
                    <td>
                        <div class="label">Invoice Type</div>
                        <div class="value">{{ $invoiceTypeLabel }}</div>
                    </td>
                    <td>
                        <div class="label">Payment Type</div>
                        <div class="value">{{ $paymentTypeLabel }}</div>
                    </td>
                </tr>
                <tr>
                    <td>
                        <div class="label">Total Amount</div>
                        <div class="value">{{ $totalAmount }}</div>
                    </td>
                    <td>
                        <div class="label">Total Paid</div>
                        <div class="value">{{ $totalPaid }}</div>
                    </td>
                </tr>
                <tr>
                    <td>
                        <div class="label">Balance Due</div>
                        <div class="value">{{ $balanceDue }}</div>
                    </td>
                    <td>
                        <div class="label">Status</div>
                        <div class="value">{{ $statusLabel }}</div>
                    </td>
                </tr>
                <tr>
                    <td>
                        <div class="label">Issued At</div>
                        <div class="value">{{ $issuedAt }}</div>
                    </td>
                    <td>
                        <div class="label">Paid At</div>
                        <div class="value">{{ $paidAt }}</div>
                    </td>
                </tr>
                <tr>
                    <td>
                        <div class="label">First Payment Amount</div>
                        <div class="value">{{ $installment['first_payment_amount'] }}</div>
                    </td>
                    <td>
                        <div class="label">Recurring Amount</div>
                        <div class="value">{{ $installment['recurring_amount'] }}</div>
                    </td>
                </tr>
                <tr>
                    <td>
                        <div class="label">Billing Day</div>
                        <div class="value">{{ $installment['billing_day'] }}</div>
                    </td>
                    <td>
                        <div class="label">Next Due Date</div>
                        <div class="value">{{ $installment['next_due_date'] }}</div>
                    </td>
                </tr>
                <tr>
                    <td>
                        <div class="label">Installment Count</div>
                        <div class="value">{{ $installment['installment_count'] }}</div>
                    </td>
                    <td>
                        <div class="label">Payments Recorded</div>
                        <div class="value">{{ $installment['installments_paid_count'] }}</div>
                    </td>
                </tr>
            </table>

            <div class="section">
                <h2 class="section-title">Payment References</h2>
                <table class="reference-table">
                    <thead>
                        <tr>
                            <th>Reference</th>
                            <th>Amount</th>
                            <th>Recorded At</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($paymentReferences as $paymentReference)
                            <tr>
                                <td>{{ $paymentReference['reference'] }}</td>
                                <td>{{ $paymentReference['amount'] }}</td>
                                <td>{{ $paymentReference['paid_at'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3">No successful payment reference has been recorded yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="footer">
                Generated on <span class="accent">{{ $generatedAt }}</span>.
                For billing support, contact <span class="accent">{{ $supportEmail }}</span>.
            </div>
        </div>
    </div>
</body>
</html>
