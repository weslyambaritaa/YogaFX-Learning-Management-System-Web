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
        }
        .label {
            font-size: 16px;
            font-weight: 700;
            margin: 0 0 6px;
        }
        .amount {
            font-size: 24px;
            font-weight: 700;
            margin: 0 0 14px;
        }
        .payment-line {
            font-size: 18px;
            font-weight: 700;
            line-height: 1.6;
            margin: 0 0 20px;
            max-width: 660px;
        }
        .footer {
            font-size: 16px;
            line-height: 1.7;
            margin-top: 20px;
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
