<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Quote #{{ $quote->quote_number }}</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 20px; 
            line-height: 1.6;
            color: #333;
        }
        .header { 
            text-align: center; 
            margin-bottom: 30px; 
            border-bottom: 2px solid #007bff;
            padding-bottom: 20px;
        }
        .quote-info { 
            margin: 20px 0; 
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
        }
        .items { 
            margin: 20px 0; 
        }
        .totals { 
            text-align: right; 
            margin: 20px 0; 
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #007bff;
            color: white;
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            font-style: italic;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>QUOTE #{{ $quote->quote_number }}</h1>
        <p>Date: {{ $quote->created_at->format('F j, Y') }}</p>
        @if($quote->valid_until)
        <p>Valid Until: {{ $quote->valid_until->format('F j, Y') }}</p>
        @endif
    </div>

    <div class="quote-info">
        <h2>Quote Details</h2>
        <p><strong>Deal:</strong> {{ $quote->deal->title ?? 'N/A' }}</p>
        <p><strong>Client:</strong> {{ $quote->deal->contact ? ($quote->deal->contact->first_name . ' ' . $quote->deal->contact->last_name) : 'N/A' }}</p>
        <p><strong>Company:</strong> {{ $quote->deal->company->name ?? 'N/A' }}</p>
        @if($quote->deal->contact && $quote->deal->contact->email)
        <p><strong>Email:</strong> {{ $quote->deal->contact->email }}</p>
        @endif
    </div>

    <div class="items">
        <h2>Items</h2>
        <table>
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
        @if($quote->discount > 0)
        <p><strong>Discount:</strong> -${{ number_format($quote->discount, 2) }}</p>
        @endif
        <p><strong>Tax:</strong> ${{ number_format($quote->tax, 2) }}</p>
        <p><strong>Total:</strong> ${{ number_format($quote->total, 2) }} {{ $quote->currency }}</p>
    </div>

    <div class="footer">
        <p>Thank you for your business!</p>
        <p>This quote is valid until {{ $quote->valid_until ? $quote->valid_until->format('F j, Y') : 'further notice' }}.</p>
    </div>
</body>
</html>

