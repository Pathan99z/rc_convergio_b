<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Quote #{{ $quote->quote_number }}</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .header { text-align: center; margin-bottom: 30px; }
        .quote-info { margin: 20px 0; }
        .items { margin: 20px 0; }
        .totals { text-align: right; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="header">
        <h1>QUOTE #{{ $quote->quote_number }}</h1>
        <p>Date: {{ $quote->created_at->format('F j, Y') }}</p>
    </div>

    <div class="quote-info">
        <h2>Quote Details</h2>
        <p><strong>Deal:</strong> {{ $quote->deal->title ?? 'N/A' }}</p>
        <p><strong>Client:</strong> {{ $quote->deal->contact ? ($quote->deal->contact->first_name . ' ' . $quote->deal->contact->last_name) : 'N/A' }}</p>
        <p><strong>Company:</strong> {{ $quote->deal->company->name ?? 'N/A' }}</p>
    </div>

    <div class="items">
        <h2>Items</h2>
        <table border="1" cellpadding="5" cellspacing="0" width="100%">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Description</th>
                    <th>Qty</th>
                    <th>Unit Price</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($quote->items as $item)
                <tr>
                    <td>{{ $item->name }}</td>
                    <td>{{ $item->description ?? '-' }}</td>
                    <td>{{ $item->quantity }}</td>
                    <td>${{ number_format($item->unit_price, 2) }}</td>
                    <td>${{ number_format($item->total, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="totals">
        <p><strong>Subtotal:</strong> ${{ number_format($quote->subtotal, 2) }}</p>
        <p><strong>Tax:</strong> ${{ number_format($quote->tax, 2) }}</p>
        <p><strong>Total:</strong> ${{ number_format($quote->total, 2) }} {{ $quote->currency }}</p>
    </div>

    <div class="footer">
        <p>Thank you for your business!</p>
    </div>
</body>
</html>
