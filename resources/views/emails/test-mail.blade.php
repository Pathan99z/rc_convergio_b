<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Email</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background-color: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;">
        <h2 style="color: #2c3e50; margin-top: 0;">Test Email</h2>
        <p>{{ $testMessage ?? 'This is a test email to verify your email configuration is working correctly.' }}</p>
        <p>If you received this email, your SMTP settings are configured properly.</p>
        <p style="margin-top: 30px; font-size: 12px; color: #666;">
            This is an automated test email from {{ config('app.name', 'RC Convergio') }}.
        </p>
    </div>
</body>
</html>

