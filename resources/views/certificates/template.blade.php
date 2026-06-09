<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $certificateLabel }}</title>
</head>
<body style="font-family: Georgia, serif; padding: 48px; color: #1f2937;">
    <p style="font-size: 14px; letter-spacing: 0.12em; text-transform: uppercase;">YogaFX LMS</p>
    <h1 style="font-size: 32px; margin-bottom: 12px;">{{ $certificateLabel }}</h1>
    <p style="font-size: 18px;">Awarded to <strong>{{ $student->name }}</strong></p>
    <p>Generated on {{ $generatedAt->format('F j, Y') }}</p>
    <p>Version {{ $version }}</p>
</body>
</html>
