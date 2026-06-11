<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $subjectLine }}</title>
</head>
<body style="font-family: Arial, sans-serif; color: #0f172a; line-height: 1.6;">
    @if (\Illuminate\Support\Str::contains($body, '<'))
        {!! $body !!}
    @else
        <div style="white-space: pre-line;">{{ $body }}</div>
    @endif
</body>
</html>
