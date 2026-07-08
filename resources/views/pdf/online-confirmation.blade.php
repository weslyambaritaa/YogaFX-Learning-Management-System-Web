<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Online Confirmation</title>
    <style>
        @page { margin: 0; }
        body { font-family: Arial, sans-serif; color: #111; font-size: 16px; line-height: 1.5; margin: 0; background: #fff; }
        .page { padding: 0 42px; position: relative; min-height: 1122px; }
        .content { padding: 0 42px; padding-bottom: 220px; }
        .footer { position: absolute; left: 42px; right: 42px; bottom: 0; }
        .watermark { position: fixed; top: 170px; left: 10%; width: 80%; z-index: -1; opacity: 0.12; }
        .watermark img { width: 100%; height: auto; object-fit: contain; }
        .watermark-fallback { position: fixed; top: 210px; right: 94px; font-size: 520px; line-height: 0.78; color: rgba(220, 38, 38, 0.12); font-weight: 900; z-index: -1; transform: rotate(10deg); }
        .header-banner { text-align: center; margin-bottom: 10px; }
        .header-banner img { width: 100%; height: auto; object-fit: contain; }
        .date-badge { text-align: right; margin-bottom: 8px; margin-top: -30px; }
        .date-badge span { display: inline-block; border: 1px solid #9ca3af; padding: 4px 12px; font-size: 12px; font-style: italic; color: #1f2937; background: #f9fafb; }
        .subtitle { text-align: center; margin-bottom: 18px; font-size: 24px; font-weight: 700; line-height: 1.45; }
        .confirm { color: #dc2626; font-style: italic; font-weight: 800; margin-top: 2px; display: block; }
        .greeting { margin: 0 0 10px; font-weight: 700; }
        .intro { margin: 0 0 12px; }
        table.meta { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .meta .k { width: 180px; color: #dc2626; font-weight: 700; }
        .meta .v { color: #111827; font-weight: 700; font-style: italic; padding-left: 12px; }
        .installments { margin: 2px 0 14px 0; width: 100%; border-collapse: collapse; border: 1px solid #000000; }
        .installments td { padding: 2px 8px; font-weight: 700; font-style: italic; border: 1px solid #000000; }
        .installments .label { width: 20%; }
        .installments .amount { width: 20%; }
        .installments .due { width: 38%; }
        .installments .status { width: 12%; text-align: right; }
        .closing { margin-top: 14px; line-height: 1.55; }
        .signature { margin-top: 12px; padding: 0 42px; text-align: left; }
        .signature img { width: 100%; object-fit: contain; object-position: left center; }
        .copyright { margin-top: 16px; text-align: center; font-size: 11px; font-style: italic; color: #374151; }
        .website { margin-top: 8px; padding: 9px 12px; text-align: center; background: #dc2626; color: #fff; font-size: 16px; font-weight: 800; letter-spacing: 0.2px; }
        .check-icon { display: inline-block; width: 14px; height: 14px; }
    </style>
</head>
<body>
    @if (filled($watermarkUrl))
        <div class="watermark"><img src="{{ $watermarkUrl }}" alt="Watermark"></div>
    @else
        <div class="watermark-fallback">Y</div>
    @endif
    <div class="page">
        @if (filled($headerUrl))
            <div class="header-banner"><img src="{{ $headerUrl }}" alt="Header Banner"></div>
        @endif

        <div class="date-badge"><span>{{ $generatedOn }}</span></div>
        <div class="subtitle">
            {{ $courseName }}<br>
            <span class="confirm">Confirmation</span>
        </div>

        <div class="content">
            <p class="greeting">Dear {{ $dearName }},</p>
            <p class="intro">We are thrilled that you will be joining us for our {{ $courseName }}.</p>

            <table class="meta">
                <tr>
                    <td class="k">Course Investment:</td>
                    <td class="v">{{ $coursePrice }}</td>
                </tr>
                @if (($paymentMode ?? 'pay_in_full') === 'pay_in_full')
                    <tr>
                        <td class="k">Full Payment Received:</td>
                        <td class="v">
                            {{ $coursePrice }} Received on {{ $paymentReceivedOn }}
                            @if (filled($greenTickUrl))
                                <img class="check-icon" src="{{ $greenTickUrl }}" alt="Paid">
                            @endif
                        </td>
                    </tr>
                @else
                    <tr>
                        <td class="k">1st Installment:</td>
                        <td class="v">
                            {{ $depositPaid }}
                            @if ($hasDepositReceived)
                                Received on {{ $depositReceivedOn }}
                                @if (filled($greenTickUrl))
                                    <img class="check-icon" src="{{ $greenTickUrl }}" alt="Paid">
                                @endif
                            @else
                                Pending
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td class="k">Balance:</td>
                        <td class="v">{{ $balanceDue }} due as follows please</td>
                    </tr>
                @endif
            </table>

            @if (($paymentMode ?? 'pay_in_full') === 'pay_in_4')
                @php
                    $installmentStatuses = collect($installmentRows ?? [])->pluck('status')->map(fn ($s) => strtolower((string) $s));
                    $isInstallmentFullyPaid = $installmentStatuses->isNotEmpty()
                        && $installmentStatuses->every(fn ($s) => $s === 'paid');
                @endphp
                <table class="installments">
                    @if ($isPayIn4)
                        <tr>
                            <td class="label">1st Installment</td>
                            <td class="amount">{{ $payIn4DepositRow['amount'] ?? $depositPaid }}</td>
                            <td class="due">
                                @if (filled($payIn4DepositRow['received_on'] ?? null))
                                    Received on {{ $payIn4DepositRow['received_on'] }}
                                @else
                                    Due on {{ $installmentRows[0]['due_on'] ?? $depositReceivedOn }}
                                @endif
                            </td>
                            <td class="status">
                                @if (($payIn4DepositRow['status'] ?? '') === 'paid')
                                    Paid <img class="check-icon" src="{{ $greenTickUrl }}" alt="Paid">
                                @elseif (($payIn4DepositRow['status'] ?? '') === 'failed')
                                    Failed
                                @elseif (($payIn4DepositRow['status'] ?? '') === 'pending')
                                    Pending
                                @else
                                    Scheduled
                                @endif
                            </td>
                        </tr>
                    @endif
                    @foreach ($installmentRows as $row)
                        @if (! $isPayIn4 || $loop->index > 0)
                            <tr>
                                <td class="label">{{ $row['label'] }}</td>
                                <td class="amount">{{ $row['amount'] }}</td>
                                <td class="due">
                                    @if (filled($row['received_on']))
                                        Received on {{ $row['received_on'] }}
                                    @else
                                        Due on {{ $row['due_on'] }}
                                    @endif
                                </td>
                                <td class="status">
                                    @if (($row['status'] ?? '') === 'paid')
                                        Paid <img class="check-icon" src="{{ $greenTickUrl }}" alt="Paid">
                                    @elseif (($row['status'] ?? '') === 'failed')
                                        Failed
                                    @elseif (($row['status'] ?? '') === 'pending')
                                        Pending
                                    @else
                                        Scheduled
                                    @endif
                                </td>
                            </tr>
                        @endif
                    @endforeach
                </table>
                @if (!empty($showFirstInstallmentFailedNotice))
                    <p style="margin:8px 0 0 174px;color:#b91c1c;font-size:12px;line-height:1.5;">
                        We couldn't process your first installment. We've added it to your next payment.
                        If you have any problems, please contact us.
                    </p>
                @endif

                @if ($isInstallmentFullyPaid)
                    <p style="margin:10px 0 0 0;font-size:14px;font-weight:700;line-height:1.5;">
                        Congratulations! Your Online Course has now been paid in full <img class="check-icon" src="{{ $greenTickUrl }}" alt="Paid">
                    </p>
                @endif
            @endif

            <p class="closing">
                Thank you for your interest in Yoga<span style="color:red">FX</span> International Yoga Teacher Training Academy<br>it really is appreciated.
            </p>

            <div style="font-size:20px;font-weight:700;">
                <strong><img src="{{ $greenTickUrl }}" class="check-icon" alt="Trophy Icon"> Bonus 1:</strong> 10 Premium Online Lectures ($490) - Module 14<br>
                <strong><img src="{{ $greenTickUrl }}" class="check-icon" alt="Trophy Icon"> Bonus 4:</strong> Meditation & Mindfulness Course ($99) - Module 15<br>
                <strong><img src="{{ $greenTickUrl }}" class="check-icon" alt="Trophy Icon"> Bonus 2:</strong> Full $197 Credit to Mr. Ian's MasterClass<br>
                <strong><img src="{{ $greenTickUrl }}" class="check-icon" alt="Trophy Icon"> Bonus 3:</strong> MasterClass Pre-Course Preparation Credit<br>
                <strong><img src="{{ $greenTickUrl }}" class="check-icon" alt="Trophy Icon"> Bonus 5:</strong> Yoga Alliance RYT-200
            </div>
        </div>

        <div class="footer">
            @if (filled($signatureUrl))
                <div class="signature"><img src="{{ $signatureUrl }}" alt="Signature Image"></div>
            @endif
            <div class="copyright">Copyright &copy; {{ date('Y') }} Yoga<span style="color:red">FX</span> International Yoga Teacher Training Academy</div>
            <div class="website">www.YogaFXTeacherTraining.com</div>
        </div>
    </div>
</body>
</html>
