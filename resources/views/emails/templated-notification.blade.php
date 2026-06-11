<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $subjectLine }}</title>
</head>
<body style="font-family: Arial, sans-serif; color: #0f172a; line-height: 1.6;">
    @if (\Illuminate\Support\Str::contains($bodyHtml, '<'))
        {!! $bodyHtml !!}
    @else
        <div style="white-space: pre-line;">{{ $bodyHtml }}</div>
    @endif
</body>
</html>