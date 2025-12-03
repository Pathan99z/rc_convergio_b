<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Quote #{{ $quote->quote_number }}</title>
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
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .quote-details {
            background-color: #fff;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .quote-items {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        .quote-items th,
        .quote-items td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left;
        }
        .quote-items th {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        .quote-totals {
            margin-top: 20px;
            text-align: right;
        }
        .total-row {
            font-weight: bold;
            font-size: 1.2em;
            color: #2c3e50;
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
        <h1>Quote #{{ $quote->quote_number }}</h1>
        <p><strong>Company:</strong> {{ $companyName }}</p>
        <p><strong>Deal:</strong> {{ $quote->deal->title }}</p>
        @if($quote->valid_until)
            <p><strong>Valid Until:</strong> {{ $quote->valid_until->format('F j, Y') }}</p>
        @endif
    </div>

    @if($customMessage)
        <div style="background-color: #e8f4f8; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
            <p><strong>Message:</strong></p>
            <p>{{ $customMessage }}</p>
        </div>
    @endif

    <div class="quote-details">
        <h2>Quote Items</h2>
        <table class="quote-items">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Description</th>
                    <th>Quantity</th>
                    <th>Unit Price</th>
                    <th>Discount</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($quote->items as $item)
                <tr>
                    <td>{{ $item->name }}</td>
                    <td>{{ $item->description ?? '-' }}</td>
                    <td>{{ $item->quantity }}</td>
                    <td>{{ formatCurrency($item->unit_price, $quote->currency) }}</td>
                    <td>{{ formatCurrency($item->discount, $quote->currency) }}</td>
                    <td>{{ formatCurrency($item->total, $quote->currency) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div class="quote-totals">
            <p><strong>Subtotal:</strong> {{ formatCurrency($quote->subtotal, $quote->currency) }}</p>
            @if($quote->discount > 0)
                <p><strong>Discount:</strong> -{{ formatCurrency($quote->discount, $quote->currency) }}</p>
            @endif
            @if($quote->tax > 0)
                <p><strong>Tax:</strong> {{ formatCurrency($quote->tax, $quote->currency) }}</p>
            @endif
            <p class="total-row"><strong>Total:</strong> {{ formatCurrency($quote->total, $quote->currency) }}</p>
        </div>
    </div>

    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ env('APP_URL') }}/api/public/quotes/{{ $quote->uuid }}/view" 
           style="background: #3B82F6; color: white; padding: 15px 30px; text-decoration: none; border-radius: 6px; font-weight: bold; display: inline-block;">
            View & Accept Quote
        </a>
    </div>

    <div class="footer">
        <p>This quote is valid until {{ $quote->valid_until ? $quote->valid_until->format('F j, Y') : 'further notice' }}.</p>
        <p>Click the button above to view the full quote details and accept or reject it online.</p>
        <p>If you have any questions about this quote, please contact us.</p>
        <p>Thank you for your business!</p>
    </div>
</body>
</html>
