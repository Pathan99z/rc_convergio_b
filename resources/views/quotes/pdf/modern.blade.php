<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Quote #{{ $quote->quote_number }}</title>
    <style>
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            margin: 0; 
            padding: 40px;
            line-height: 1.6;
            color: #2c3e50;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .header { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
            text-align: center;
        }
        .quote-info { 
            margin: 0; 
            background: #f8f9fa;
            padding: 30px;
            border-bottom: 1px solid #e9ecef;
        }
        .items { 
            margin: 0; 
            padding: 30px;
        }
        .totals { 
            background: #f8f9fa;
            padding: 30px;
            border-top: 3px solid #667eea;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        th, td {
            padding: 15px;
            text-align: left;
        }
        th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            font-weight: 600;
        }
        tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        tr:hover {
            background-color: #e3f2fd;
        }
        .footer {
            background: #2c3e50;
            color: white;
            padding: 30px;
            text-align: center;
        }
        h1 {
            font-size: 36px;
            margin: 0;
            font-weight: 300;
        }
        h2 {
            color: #667eea;
            font-size: 24px;
            margin-bottom: 20px;
            font-weight: 600;
        }
        .total-amount {
            font-size: 24px;
            font-weight: bold;
            color: #667eea;
            text-align: right;
            margin-top: 20px;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            background: #28a745;
            color: white;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>QUOTE</h1>
            <h2>#{{ $quote->quote_number }}</h2>
            <p style="font-size: 18px; margin: 10px 0;">{{ $quote->created_at->format('F j, Y') }}</p>
            @if($quote->valid_until)
            <p style="font-size: 16px;">Valid Until: {{ $quote->valid_until->format('F j, Y') }}</p>
            @endif
            <div class="status-badge">{{ ucfirst($quote->status) }}</div>
        </div>

        <div class="quote-info">
            <h2>Client Details</h2>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div>
                    <p><strong>Deal:</strong> {{ $quote->deal->title ?? 'N/A' }}</p>
                    <p><strong>Client:</strong> {{ $quote->deal->contact ? ($quote->deal->contact->first_name . ' ' . $quote->deal->contact->last_name) : 'N/A' }}</p>
                </div>
                <div>
                    <p><strong>Company:</strong> {{ $quote->deal->company->name ?? 'N/A' }}</p>
                    @if($quote->deal->contact && $quote->deal->contact->email)
                    <p><strong>Email:</strong> {{ $quote->deal->contact->email }}</p>
                    @endif
                </div>
            </div>
        </div>

        <div class="items">
            <h2>Items & Services</h2>
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
                        <td><strong>{{ $item->name }}</strong></td>
                        <td>{{ $item->description ?? '-' }}</td>
                        <td>{{ $item->quantity }}</td>
                        <td>{{ formatCurrency($item->unit_price, $quote->currency) }}</td>
                        <td><strong>{{ formatCurrency($item->total, $quote->currency) }}</strong></td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="totals">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <p><strong>Subtotal:</strong> {{ formatCurrency($quote->subtotal, $quote->currency) }}</p>
                    @if($quote->discount > 0)
                    <p><strong>Discount:</strong> -{{ formatCurrency($quote->discount, $quote->currency) }}</p>
                    @endif
                    <p><strong>Tax:</strong> {{ formatCurrency($quote->tax, $quote->currency) }}</p>
                </div>
                <div class="total-amount">
                    <div>TOTAL</div>
                    <div>{{ formatCurrency($quote->total, $quote->currency) }}</div>
                </div>
            </div>
        </div>

        <div class="footer">
            <p style="font-size: 18px; margin: 0 0 10px 0;">Thank you for choosing our services!</p>
            <p style="margin: 0 0 20px 0;">This quote is valid until {{ $quote->valid_until ? $quote->valid_until->format('F j, Y') : 'further notice' }}.</p>
            <p style="margin-top: 20px; font-size: 11px; color: rgba(255,255,255,0.7); border-top: 1px solid rgba(255,255,255,0.2); padding-top: 15px;">
                Powered by <strong>RC Convergio</strong>
            </p>
        </div>
    </div>
</body>
</html>

