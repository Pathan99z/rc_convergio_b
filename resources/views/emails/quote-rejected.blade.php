<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Quote Rejected - #{{ $quote->quote_number }}</title>
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
            background-color: #f8d7da;
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
        <h1>Quote Rejected</h1>
        <p><strong>Quote #{{ $quote->quote_number }}</strong></p>
    </div>

    <div class="content">
        <h2>Quote Status Update</h2>
        <p>Unfortunately, your quote <strong>#{{ $quote->quote_number }}</strong> for <strong>{{ $quote->deal->title }}</strong> has been rejected by the client.</p>

        <div class="quote-summary">
            <h3>Quote Summary</h3>
            <p><strong>Deal:</strong> {{ $quote->deal->title }}</p>
            <p><strong>Company:</strong> {{ $quote->deal->company->name ?? 'N/A' }}</p>
            <p><strong>Total Amount:</strong> ${{ number_format($quote->total, 2) }} {{ $quote->currency }}</p>
            <p><strong>Rejected On:</strong> {{ now()->format('F j, Y \a\t g:i A') }}</p>
        </div>

        <h3>Recommended Actions</h3>
        <ul>
            <li>Review the quote details and pricing</li>
            <li>Consider reaching out to the client for feedback</li>
            <li>Analyze what might have led to the rejection</li>
            <li>Consider creating a new quote with adjusted terms</li>
            <li>Update your sales strategy for similar deals</li>
        </ul>

        <h3>Learning Opportunity</h3>
        <p>Every rejection is a chance to learn and improve. Consider:</p>
        <ul>
            <li>Was the pricing competitive?</li>
            <li>Did the quote meet the client's needs?</li>
            <li>Was the value proposition clear?</li>
            <li>Could the timeline or terms be adjusted?</li>
        </ul>
    </div>

    <div class="footer">
        <p>This notification was automatically generated when the quote was rejected.</p>
        <p>Don't let this setback discourage you - use it as a stepping stone to success!</p>
    </div>
</body>
</html>

