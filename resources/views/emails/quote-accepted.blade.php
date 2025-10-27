<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Quote Accepted - #{{ $quote->quote_number }}</title>
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
            background-color: #d4edda;
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
        <h1>🎉 Quote Accepted!</h1>
        <p><strong>Quote #{{ $quote->quote_number }}</strong></p>
    </div>

    <div class="content">
        <h2>Great News!</h2>
        <p>Your quote <strong>#{{ $quote->quote_number }}</strong> for <strong>{{ $quote->deal->title }}</strong> has been accepted by the client!</p>

        <div class="quote-summary">
            <h3>Quote Summary</h3>
            <p><strong>Deal:</strong> {{ $quote->deal->title }}</p>
            <p><strong>Company:</strong> {{ $quote->deal->company->name ?? 'N/A' }}</p>
            <p><strong>Total Amount:</strong> ${{ number_format($quote->total, 2) }} {{ $quote->currency }}</p>
            <p><strong>Accepted On:</strong> {{ now()->format('F j, Y \a\t g:i A') }}</p>
        </div>

        <h3>Next Steps</h3>
        <ul>
            <li>The deal has been automatically marked as <strong>Won</strong></li>
            <li>The deal's close date has been set to today</li>
            <li>You can now proceed with project execution</li>
            <li>Remember to follow up with the client for contract signing</li>
        </ul>
    </div>

    <div class="footer">
        <p>This notification was automatically generated when the quote was accepted.</p>
        <p>Congratulations on closing this deal!</p>
    </div>
</body>
</html>

