<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body style="margin: 0; padding: 16px; font-family: Arial, Helvetica, sans-serif; font-size: 15px; line-height: 1.5; color: #1f2937;">
    <div>{!! nl2br(e($body)) !!}</div>

    <p style="margin-top: 32px; font-size: 12px; color: #9ca3af;">
        Don't want these emails? <a href="{{ $unsubscribeUrl }}" style="color: #9ca3af;">Unsubscribe</a>.
    </p>
</body>
</html>
