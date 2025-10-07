<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Quote Expiring Soon - #{{ $quote->quote_number }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #fff3cd;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }
        .content {
            background-color: #fff;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .quote-summary {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .urgent {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            font-size: 0.9em;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>⚠️ Quote Expiring Soon</h1>
        <p><strong>Quote #{{ $quote->quote_number }}</strong></p>
    </div>

    <div class="content">
        <h2>Action Required</h2>
        <p>Dear {{ $contact->name }},</p>
        <p>This is a friendly reminder that your quote <strong>#{{ $quote->quote_number }}</strong> for <strong>{{ $quote->deal->title }}</strong> will expire soon.</p>

        @if($quote->valid_until)
            <div class="urgent">
                <h3>⏰ Expiration Date</h3>
                <p><strong>{{ $quote->valid_until->format('F j, Y \a\t g:i A') }}</strong></p>
                <p>Time remaining: {{ $quote->valid_until->diffForHumans() }}</p>
            </div>
        @endif

        <div class="quote-summary">
            <h3>Quote Summary</h3>
            <p><strong>Deal:</strong> {{ $quote->deal->title }}</p>
            <p><strong>Company:</strong> {{ $quote->deal->company->name ?? 'Your Company' }}</p>
            <p><strong>Total Amount:</strong> ${{ number_format($quote->total, 2) }} {{ $quote->currency }}</p>
        </div>

        <h3>What You Need to Do</h3>
        <ul>
            <li><strong>Review the quote details</strong> - Make sure all items and pricing are correct</li>
            <li><strong>Make a decision</strong> - Accept or reject the quote before it expires</li>
            <li><strong>Contact us</strong> - If you have any questions or need clarification</li>
            <li><strong>Request an extension</strong> - If you need more time to decide</li>
        </ul>

        <h3>Benefits of Acting Now</h3>
        <ul>
            <li>Secure the current pricing and terms</li>
            <li>Lock in project timeline and availability</li>
            <li>Avoid potential price increases</li>
            <li>Ensure smooth project kickoff</li>
        </ul>

        <div style="text-align: center; margin: 30px 0;">
            <p><strong>Need help making a decision?</strong></p>
            <p>We're here to answer any questions you might have about this quote.</p>
            <p>Contact us today to discuss your needs and concerns.</p>
        </div>
    </div>

    <div class="footer">
        <p>This is an automated reminder about your quote expiration.</p>
        <p>If you have already made a decision, please ignore this email.</p>
        <p>Thank you for considering our services!</p>
    </div>
</body>
</html>

