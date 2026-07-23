<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Online Payment Confirmation</title>
    <style>
        @page { margin: 0; }
        body { font-family: Arial, sans-serif; color: #111; font-size: 16px; line-height: 1.5; margin: 0; background: #fff; }
        .page {
            padding: 0 42px;
            position: relative;
            min-height: 1122px;
        }
        .content { padding: 0 42px; padding-bottom: 220px; }
        .footer {
            position: absolute;
            left: 42px;
            right: 42px;
            bottom: 0;
        }
        .watermark {
            position: fixed;
            top: 170px;
            left: 10%;
            width: 80%;
            z-index: -1;
            opacity: 0.12;
        }
        .watermark img {
            width: 100%;
            height: auto;
            object-fit: contain;
        }
        .watermark-fallback {
            position: fixed;
            top: 210px;
            right: 94px;
            font-size: 520px;
            line-height: 0.78;
            color: rgba(220, 38, 38, 0.12);
            font-weight: 900;
            z-index: -1;
            transform: rotate(10deg);
        }
        .header-banner { text-align: center; margin-bottom: 10px; }
        .header-banner img { width: 100%; height: auto; object-fit: contain; }
        .date-badge { text-align: right; margin-bottom: 8px; margin-top:-30px; }
        .date-badge span {
            display: inline-block;
            border: 1px solid #9ca3af;
            padding: 4px 12px;
            font-size: 12px;
            font-style: italic;
            color: #1f2937;
            background: #f9fafb;
        }
        .subtitle { text-align: center; margin-bottom: 18px; font-size: 18px; font-weight: 700; line-height: 1.45; }
        .confirm {
            color: #dc2626;
            font-style: italic;
            font-weight: 800;
            margin-top: 2px;
            display: block;
        }
        .greeting { margin: 0 0 10px; font-weight: 700; }
        .intro { margin: 0 0 12px; }
        table.meta {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
        }
        .meta td { }
        .meta .k {
            width: 172px;
            color: #dc2626;
            font-weight: 700;
        }
        .meta .v { color: #111827; font-weight: 700; font-style: italic; padding-left: 12px; }
        .closing { margin-top: 14px; line-height: 1.55; }
        .signature { margin-top: 12px; padding: 0 42px; text-align: left; }
        .signature img {
            width: 100%;
            object-fit: contain;
            object-position: left center;
        }
        .copyright {
            margin-top: 16px;
            text-align: center;
            font-size: 11px;
            font-style: italic;
            color: #374151;
        }
        .website {
            margin-top: 8px;
            padding: 9px 12px;
            text-align: center;
            background: #dc2626;
            color: #fff;
            font-size: 16px;
            font-weight: 800;
            letter-spacing: 0.2px;
        }
        .check-icon {
            display: inline-block;
            width: 14px;
            height: 14px;
            margin-left: 6px;
            vertical-align: -2px;
        }
        .line { margin: 0 0 4px; }
    </style>
</head>
<body>
    @if (filled($watermarkUrl))
        <div class="watermark">
            <img src="{{ $watermarkUrl }}" alt="Watermark">
        </div>
    @else
        <div class="watermark-fallback">Y</div>
    @endif
    <div class="page">
        @if (filled($headerUrl))
            <div class="header-banner">
                <img src="{{ $headerUrl }}" alt="Header Banner">
            </div>
        @else
            <p class="line">[Header Banner]</p>
        @endif

        <div class="date-badge"><span>{{ $generatedOn }}</span></div>
        <div class="subtitle">
            {{ $courseName }}<br>
            <span class="confirm">Confirmation</span>
        </div>

        <div class="content">
            <p class="greeting">Dear {{ $dearName }},</p>
            <p class="intro">We are thrilled that you will be joining us for our online training.</p>

            <table class="meta">
                <tr>
                    <td class="k">Course Investment</td>
                    <td class="v">{{ $courseName }} {{ $coursePrice }}</td>
                </tr>
                <tr>
                    <td class="k">Payment</td>
                    <td class="v">
                        {{ $coursePrice }} Received on {{ $paymentReceivedOn }}
                        @if (filled($greenTickUrl))
                            <img class="check-icon" src="{{ $greenTickUrl }}" alt="Paid">
                        @endif
                    </td>
                </tr>
            </table>

            <p class="closing">
                Thank you for your interest in YogaFX International Yoga Teacher Training Academy it really is appreciated.
            </p>
        </div>

        <div class="footer">
            @if (filled($signatureUrl))
                <div class="signature">
                    <img src="{{ $signatureUrl }}" alt="Signature Image">
                </div>
            @else
                <p class="line">[Signature Image]</p>
            @endif

            <div class="copyright">Copyright &copy; {{ date('Y') }} YogaFX International Yoga Teacher Training Academy</div>
            <div class="website">www.YogaFXTeacherTraining.com</div>
        </div>
    </div>
</body>
</html>
