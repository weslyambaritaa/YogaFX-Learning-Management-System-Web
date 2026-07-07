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
        .label {
            font-size: 16px;
            font-weight: 700;
            margin: 0 0 6px;
        }
        .amount {
            font-size: 24px;
            font-weight: 700;
            margin: 0 0 16px;
        }
        .payment-line {
            font-size: 18px;
            font-weight: 700;
            line-height: 1.6;
            margin: 0 0 24px;
        }
        .footer {
            font-size: 16px;
            line-height: 1.7;
            margin-top: 26px;
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

        <p class="label">Course Investment:</p>
        <p class="amount">{{ $coursePrice }}</p>

        <p class="payment-line">
            Full Payment Received: {{ $fullPaymentAmount }} Received on {{ $paymentReceivedOn }}
        </p>

        <p class="footer">
            Thank you for your interest in YogaFX International Yoga Teacher Training Academy<br>
            it really is appreciated
        </p>
    </div>
</body>
</html>
