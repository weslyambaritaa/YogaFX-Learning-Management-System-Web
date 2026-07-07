<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $documentTitle }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #111827;
            margin: 0;
            background: #ffffff;
        }
        .page {
            padding: 32px 42px 38px;
            max-width: 720px;
        }
        .title {
            font-size: 24px;
            font-weight: 700;
            line-height: 1.18;
            margin: 0 0 2px;
            max-width: 640px;
        }
        .subtitle {
            font-size: 22px;
            font-weight: 700;
            margin: 0 0 24px;
        }
        .paragraph {
            font-size: 16px;
            line-height: 1.6;
            margin: 0 0 16px;
            max-width: 660px;
        }
        .summary-table {
            width: 100%;
            border-collapse: collapse;
            margin: 6px 0 14px;
            table-layout: fixed;
        }
        .summary-table td {
            vertical-align: top;
            padding: 0 14px 8px 0;
            width: 33.33%;
        }
        .label {
            font-size: 16px;
            font-weight: 700;
            margin: 0 0 6px;
        }
        .amount {
            font-size: 24px;
            font-weight: 700;
            margin: 0;
        }
        .balance-copy {
            font-size: 18px;
            font-weight: 700;
            line-height: 1.55;
            margin: 0 0 14px;
            max-width: 660px;
        }
        .schedule {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
            table-layout: fixed;
        }
        .schedule td {
            vertical-align: top;
            padding: 0 0 11px;
        }
        .schedule-left {
            width: 46%;
        }
        .schedule-right {
            width: 54%;
            padding-left: 18px;
        }
        .installment-label {
            font-size: 16px;
            font-weight: 700;
            margin: 0 0 2px;
        }
        .installment-amount {
            font-size: 18px;
            font-weight: 700;
            margin: 0;
        }
        .installment-status {
            font-size: 16px;
            font-weight: 700;
            margin: 0 0 1px;
        }
        .installment-date {
            font-size: 15px;
            line-height: 1.45;
            margin: 0;
        }
        .footer {
            font-size: 16px;
            line-height: 1.7;
            margin-top: 18px;
            max-width: 660px;
        }
    </style>
</head>
<body>
    <div class="page">
        <h1 class="title">{{ $courseName }}</h1>
        <div class="subtitle">Confirmation</div>

        <p class="paragraph">Dear {{ $dearName }},</p>

        <p class="paragraph">
            We are thrilled that you will be joining us for our {{ $courseName }}.
        </p>

        <table class="summary-table">
            <tr>
                <td>
                    <p class="label">Course Investment:</p>
                </td>
                <td>
                    <p class="label">1st Installment:</p>
                </td>
                <td>
                    <p class="label">Balance:</p>
                </td>
            </tr>
            <tr>
                <td>
                    <p class="amount">{{ $coursePrice }}</p>
                </td>
                <td>
                    <p class="amount">{{ $firstInstallmentAmount }}</p>
                </td>
                <td>
                    <p class="amount">{{ $balanceDue }}</p>
                </td>
            </tr>
        </table>

        <p class="balance-copy">
            {{ $firstInstallmentAmount }} Received on {{ $firstInstallmentReceivedOn }}<br>
            @if ($showBalanceScheduleCopy)
                {{ $balanceDue }} due as follows please
            @endif
        </p>

        <table class="schedule">
            @foreach ($installmentRows as $row)
                <tr>
                    <td class="schedule-left">
                        <p class="installment-label">{{ $row['label'] }}</p>
                        <p class="installment-amount">{{ $row['amount'] }}</p>
                    </td>
                    <td class="schedule-right">
                        @if ($row['show_status'] && $row['status_first'])
                            <p class="installment-status">{{ $row['status_label'] }}</p>
                        @endif
                        <p class="installment-date">{{ $row['date_label'] }} {{ $row['date_value'] }}</p>
                        @if ($row['show_status'] && ! $row['status_first'])
                            <p class="installment-status">{{ $row['status_label'] }}</p>
                        @endif
                    </td>
                </tr>
            @endforeach
        </table>

        <p class="footer">
            Thank you for your interest in YogaFX International Yoga Teacher Training Academy<br>
            it really is appreciated
        </p>
    </div>
</body>
</html>
