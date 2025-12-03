<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Quote #{{ $quote->quote_number }}</title>
    <style>
        body { 
            font-family: 'Times New Roman', serif; 
            margin: 40px; 
            line-height: 1.8;
            color: #2c3e50;
            background: #fff;
        }
        .header { 
            text-align: center; 
            margin-bottom: 40px; 
            border-bottom: 3px double #2c3e50;
            padding-bottom: 30px;
        }
        .quote-info { 
            margin: 30px 0; 
            background: #ecf0f1;
            padding: 25px;
            border-left: 5px solid #2c3e50;
        }
        .items { 
            margin: 30px 0; 
        }
        .totals { 
            text-align: right; 
            margin: 30px 0; 
            background: #ecf0f1;
            padding: 25px;
            border: 2px solid #2c3e50;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
            border: 2px solid #2c3e50;
        }
        th, td {
            border: 1px solid #2c3e50;
            padding: 12px;
            text-align: left;
        }
        th {
            background-color: #2c3e50;
            color: white;
            font-weight: bold;
        }
        .footer {
            margin-top: 40px;
            text-align: center;
            font-style: italic;
            color: #7f8c8d;
            border-top: 1px solid #bdc3c7;
            padding-top: 20px;
        }
        h1 {
            color: #2c3e50;
            font-size: 28px;
            margin-bottom: 10px;
        }
        h2 {
            color: #2c3e50;
            font-size: 20px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>QUOTATION</h1>
        <h2>Quote #{{ $quote->quote_number }}</h2>
        <p><strong>Date:</strong> {{ $quote->created_at->format('F j, Y') }}</p>
        @if($quote->valid_until)
        <p><strong>Valid Until:</strong> {{ $quote->valid_until->format('F j, Y') }}</p>
        @endif
    </div>

    <div class="quote-info">
        <h2>Client Information</h2>
        <p><strong>Deal:</strong> {{ $quote->deal->title ?? 'N/A' }}</p>
        <p><strong>Client Name:</strong> {{ $quote->deal->contact ? ($quote->deal->contact->first_name . ' ' . $quote->deal->contact->last_name) : 'N/A' }}</p>
        <p><strong>Company:</strong> {{ $quote->deal->company->name ?? 'N/A' }}</p>
        @if($quote->deal->contact && $quote->deal->contact->email)
        <p><strong>Email Address:</strong> {{ $quote->deal->contact->email }}</p>
        @endif
        @if($quote->deal->contact && $quote->deal->contact->phone)
        <p><strong>Phone:</strong> {{ $quote->deal->contact->phone }}</p>
        @endif
    </div>

    <div class="items">
        <h2>Items & Services</h2>
        <table>
            <thead>
                <tr>
                    <th>Description</th>
                    <th>Details</th>
                    <th>Quantity</th>
                    <th>Unit Price</th>
                    <th>Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach($quote->items as $item)
                <tr>
                    <td><strong>{{ $item->name }}</strong></td>
                    <td>{{ $item->description ?? 'No additional details' }}</td>
                    <td>{{ $item->quantity }}</td>
                    <td>{{ formatCurrency($item->unit_price, $quote->currency) }}</td>
                    <td><strong>{{ formatCurrency($item->total, $quote->currency) }}</strong></td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="totals">
        <h2>Summary</h2>
        <p><strong>Subtotal:</strong> {{ formatCurrency($quote->subtotal, $quote->currency) }}</p>
        @if($quote->discount > 0)
        <p><strong>Discount Applied:</strong> -{{ formatCurrency($quote->discount, $quote->currency) }}</p>
        @endif
        <p><strong>Tax ({{ $quote->items->first()->tax_rate ?? 0 }}%):</strong> {{ formatCurrency($quote->tax, $quote->currency) }}</p>
        <hr style="border: 1px solid #2c3e50; margin: 10px 0;">
        <p style="font-size: 18px; font-weight: bold;"><strong>TOTAL AMOUNT:</strong> {{ formatCurrency($quote->total, $quote->currency) }}</p>
    </div>

    <div class="footer">
        <p>We appreciate your consideration of our services.</p>
        <p>This quotation is valid until {{ $quote->valid_until ? $quote->valid_until->format('F j, Y') : 'further notice' }}.</p>
        <p>Please contact us if you have any questions regarding this quotation.</p>
        <p style="margin-top: 20px; font-size: 10px; color: #999; border-top: 1px solid #bdc3c7; padding-top: 15px;">
            Powered by <strong>RC Convergio</strong>
        </p>
    </div>
</body>
</html>

