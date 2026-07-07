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
            padding: 34px 44px 40px;
        }
        .title {
            font-size: 26px;
            font-weight: 700;
            line-height: 1.2;
            margin: 0 0 4px;
        }
        .subtitle {
            font-size: 23px;
            font-weight: 700;
            margin: 0 0 28px;
        }
        .paragraph {
            font-size: 16px;
            line-height: 1.65;
            margin: 0 0 18px;
        }
        .summary-table {
            width: 100%;
            border-collapse: collapse;
            margin: 8px 0 18px;
        }
        .summary-table td {
            vertical-align: top;
            padding: 0 20px 10px 0;
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
            margin: 0 0 18px;
        }
        .schedule {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .schedule td {
            vertical-align: top;
            padding: 0 0 14px;
        }
        .schedule-left {
            width: 48%;
        }
        .schedule-right {
            width: 52%;
            padding-left: 24px;
        }
        .installment-label {
            font-size: 16px;
            font-weight: 700;
            margin: 0 0 4px;
        }
        .installment-amount {
            font-size: 18px;
            font-weight: 700;
            margin: 0;
        }
        .installment-status {
            font-size: 16px;
            font-weight: 700;
            margin: 0 0 4px;
        }
        .installment-date {
            font-size: 15px;
            line-height: 1.55;
            margin: 0;
        }
        .footer {
            font-size: 16px;
            line-height: 1.7;
            margin-top: 22px;
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
                        @if ($row['show_status'])
                            <p class="installment-status">{{ $row['status_label'] }}</p>
                        @endif
                        <p class="installment-date">{{ $row['date_label'] }} {{ $row['date_value'] }}</p>
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
