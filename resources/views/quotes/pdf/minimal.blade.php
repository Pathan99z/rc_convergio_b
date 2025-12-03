<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Quote #{{ $quote->quote_number }}</title>
    <style>
        body { 
            font-family: 'Helvetica Neue', Arial, sans-serif; 
            margin: 0; 
            padding: 40px;
            line-height: 1.4;
            color: #333;
            background: #fff;
        }
        .header { 
            text-align: center; 
            margin-bottom: 40px; 
            border-bottom: 1px solid #ddd;
            padding-bottom: 20px;
        }
        .quote-info { 
            margin: 30px 0; 
            padding: 0;
        }
        .items { 
            margin: 30px 0; 
        }
        .totals { 
            text-align: right; 
            margin: 30px 0; 
            border-top: 1px solid #ddd;
            padding-top: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 10px 0;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        th {
            font-weight: 600;
            color: #666;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .footer {
            margin-top: 40px;
            text-align: center;
            color: #999;
            font-size: 12px;
        }
        h1 {
            font-size: 24px;
            margin: 0;
            font-weight: 300;
            color: #333;
        }
        h2 {
            font-size: 16px;
            margin: 20px 0 10px 0;
            font-weight: 600;
            color: #333;
        }
        .total-line {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin-top: 10px;
        }
        .client-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin: 20px 0;
        }
        .client-info p {
            margin: 5px 0;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Quote #{{ $quote->quote_number }}</h1>
        <p>{{ $quote->created_at->format('F j, Y') }}</p>
        @if($quote->valid_until)
        <p>Valid until {{ $quote->valid_until->format('F j, Y') }}</p>
        @endif
    </div>

    <div class="quote-info">
        <div class="client-info">
            <div>
                <h2>Client</h2>
                <p><strong>{{ $quote->deal->contact ? ($quote->deal->contact->first_name . ' ' . $quote->deal->contact->last_name) : 'N/A' }}</strong></p>
                <p>{{ $quote->deal->company->name ?? 'N/A' }}</p>
                @if($quote->deal->contact && $quote->deal->contact->email)
                <p>{{ $quote->deal->contact->email }}</p>
                @endif
            </div>
            <div>
                <h2>Project</h2>
                <p>{{ $quote->deal->title ?? 'N/A' }}</p>
            </div>
        </div>
    </div>

    <div class="items">
        <table>
            <thead>
                <tr>
                    <th>Description</th>
                    <th style="text-align: center;">Qty</th>
                    <th style="text-align: right;">Rate</th>
                    <th style="text-align: right;">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach($quote->items as $item)
                <tr>
                    <td>
                        <strong>{{ $item->name }}</strong>
                        @if($item->description)
                        <br><span style="color: #666; font-size: 12px;">{{ $item->description }}</span>
                        @endif
                    </td>
                    <td style="text-align: center;">{{ $item->quantity }}</td>
                    <td style="text-align: right;">{{ formatCurrency($item->unit_price, $quote->currency) }}</td>
                    <td style="text-align: right;"><strong>{{ formatCurrency($item->total, $quote->currency) }}</strong></td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="totals">
        <p>Subtotal: {{ formatCurrency($quote->subtotal, $quote->currency) }}</p>
        @if($quote->discount > 0)
        <p>Discount: -{{ formatCurrency($quote->discount, $quote->currency) }}</p>
        @endif
        <p>Tax: {{ formatCurrency($quote->tax, $quote->currency) }}</p>
        <div class="total-line">Total: {{ formatCurrency($quote->total, $quote->currency) }}</div>
    </div>

    <div class="footer">
        <p>Thank you for your business.</p>
        @if($quote->valid_until)
        <p>Valid until {{ $quote->valid_until->format('F j, Y') }}.</p>
        @endif
        <p style="margin-top: 20px; font-size: 10px; color: #999; border-top: 1px solid #eee; padding-top: 10px;">
            Powered by <strong>RC Convergio</strong>
        </p>
    </div>
</body>
</html>

