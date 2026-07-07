<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $invoice->invoice_number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #0f172a; margin: 0; background: #eef2f7; }
        .page { padding: 34px; }
        .card { background: #ffffff; border: 1px solid #d6dee8; border-radius: 20px; padding: 30px; }
        .hero { padding: 20px 22px; border-radius: 16px; background: linear-gradient(135deg, #f8fafc 0%, #eef2ff 100%); border: 1px solid #e2e8f0; }
        .eyebrow { font-size: 11px; text-transform: uppercase; letter-spacing: 0.24em; color: #64748b; }
        .title { font-size: 28px; font-weight: 700; margin: 10px 0 8px; }
        .subtitle { margin: 0; color: #475569; font-size: 13px; line-height: 1.65; }
        .summary { width: 100%; margin-top: 22px; }
        .summary td { width: 33.33%; padding: 0 10px 0 0; vertical-align: top; }
        .summary-card { border: 1px solid #e2e8f0; border-radius: 14px; padding: 14px; background: #fff; }
        .summary-label { font-size: 10px; letter-spacing: 0.16em; text-transform: uppercase; color: #94a3b8; margin-bottom: 6px; }
        .summary-value { font-size: 18px; font-weight: 700; color: #0f172a; }
        .section { margin-top: 24px; }
        .section-title { font-size: 14px; font-weight: 700; margin: 0 0 12px; }
        .detail-grid { width: 100%; }
        .detail-grid td { width: 50%; vertical-align: top; padding: 0 12px 14px 0; }
        .label { font-size: 10px; text-transform: uppercase; letter-spacing: 0.16em; color: #94a3b8; margin-bottom: 6px; }
        .value { font-size: 13px; font-weight: 600; color: #0f172a; }
        .reference-table { width: 100%; border-collapse: collapse; font-size: 12px; }
        .reference-table th, .reference-table td { text-align: left; padding: 10px 0; border-bottom: 1px solid #e2e8f0; }
        .reference-table th { font-size: 11px; color: #64748b; text-transform: uppercase; letter-spacing: 0.14em; }
        .footer { margin-top: 24px; color: #64748b; font-size: 12px; line-height: 1.7; }
    </style>
</head>
<body>
    <div class="page">
        <div class="card">
            <div class="hero">
                <div class="eyebrow">YogaFX LMS</div>
                <h1 class="title">Installment Confirmation</h1>
                <p class="subtitle">
                    This confirmation records the current installment arrangement, invoice standing, and all successful payment references connected to this YogaFX package.
                </p>
            </div>

            <table class="summary" cellspacing="0" cellpadding="0">
                <tr>
                    <td>
                        <div class="summary-card">
                            <div class="summary-label">Total Amount</div>
                            <div class="summary-value">{{ $totalAmount }}</div>
                        </div>
                    </td>
                    <td>
                        <div class="summary-card">
                            <div class="summary-label">Total Paid</div>
                            <div class="summary-value">{{ $totalPaid }}</div>
                        </div>
                    </td>
                    <td>
                        <div class="summary-card">
                            <div class="summary-label">Balance Due</div>
                            <div class="summary-value">{{ $balanceDue }}</div>
                        </div>
                    </td>
                </tr>
            </table>

            <div class="section">
                <h2 class="section-title">Invoice & Student Detail</h2>
                <table class="detail-grid" cellspacing="0" cellpadding="0">
                    <tr>
                        <td><div class="label">Student Name</div><div class="value">{{ $studentName }}</div></td>
                        <td><div class="label">Email</div><div class="value">{{ $studentEmail }}</div></td>
                    </tr>
                    <tr>
                        <td><div class="label">Package</div><div class="value">{{ $packageTitle }}</div></td>
                        <td><div class="label">Tier</div><div class="value">{{ $tierName }}</div></td>
                    </tr>
                    <tr>
                        <td><div class="label">Invoice Number</div><div class="value">{{ $invoice->invoice_number }}</div></td>
                        <td><div class="label">Status</div><div class="value">{{ $statusLabel }}</div></td>
                    </tr>
                    <tr>
                        <td><div class="label">Invoice Type</div><div class="value">{{ $invoiceTypeLabel }}</div></td>
                        <td><div class="label">Payment Type</div><div class="value">{{ $paymentTypeLabel }}</div></td>
                    </tr>
                    <tr>
                        <td><div class="label">Issued At</div><div class="value">{{ $issuedAt }}</div></td>
                        <td><div class="label">Paid At</div><div class="value">{{ $paidAt }}</div></td>
                    </tr>
                </table>
            </div>

            <div class="section">
                <h2 class="section-title">Installment Plan Detail</h2>
                <table class="detail-grid" cellspacing="0" cellpadding="0">
                    <tr>
                        <td><div class="label">First Payment Amount</div><div class="value">{{ $installment['first_payment_amount'] }}</div></td>
                        <td><div class="label">Recurring Amount</div><div class="value">{{ $installment['recurring_amount'] }}</div></td>
                    </tr>
                    <tr>
                        <td><div class="label">Billing Day</div><div class="value">{{ $installment['billing_day'] }}</div></td>
                        <td><div class="label">Next Due Date</div><div class="value">{{ $installment['next_due_date'] }}</div></td>
                    </tr>
                    <tr>
                        <td><div class="label">Installment Count</div><div class="value">{{ $installment['installment_count'] }}</div></td>
                        <td><div class="label">Installments Paid</div><div class="value">{{ $installment['installments_paid_count'] }}</div></td>
                    </tr>
                    <tr>
                        <td><div class="label">Final Due Date</div><div class="value">{{ $installment['final_due_date'] }}</div></td>
                        <td><div class="label">Primary Payment Reference</div><div class="value">{{ $primaryPaymentReference }}</div></td>
                    </tr>
                </table>
            </div>

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
                Generated on {{ $generatedAt }}. For billing support, contact {{ $supportEmail }}.
            </div>
        </div>
    </div>
</body>
</html>
