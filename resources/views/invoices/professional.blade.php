<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #{{ $invoice->stripe_invoice_id }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
            color: #333;
            background: #fff;
        }
        
        .invoice-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 40px;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 2px solid {{ $branding->primary_color }};
        }
        
        .company-info {
            flex: 1;
        }
        
        .logo {
            max-height: 60px;
            margin-bottom: 10px;
        }
        
        .company-name {
            font-size: 28px;
            font-weight: bold;
            color: {{ $branding->primary_color }};
            margin-bottom: 5px;
        }
        
        .company-details {
            color: #666;
            font-size: 14px;
            line-height: 1.4;
        }
        
        .invoice-info {
            text-align: right;
            flex: 1;
        }
        
        .invoice-title {
            font-size: 32px;
            font-weight: bold;
            color: {{ $branding->secondary_color }};
            margin-bottom: 10px;
        }
        
        .invoice-number {
            font-size: 18px;
            color: #666;
            margin-bottom: 20px;
        }
        
        .invoice-details {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid {{ $branding->primary_color }};
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }
        
        .detail-label {
            font-weight: 600;
            color: #333;
        }
        
        .detail-value {
            color: #666;
        }
        
        .customer-section {
            margin: 40px 0;
        }
        
        .section-title {
            font-size: 18px;
            font-weight: bold;
            color: {{ $branding->primary_color }};
            margin-bottom: 15px;
            padding-bottom: 5px;
            border-bottom: 1px solid #eee;
        }
        
        .customer-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
        }
        
        .customer-name {
            font-size: 20px;
            font-weight: bold;
            color: #333;
            margin-bottom: 10px;
        }
        
        .customer-details {
            color: #666;
            line-height: 1.5;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 40px 0;
            background: #fff;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-radius: 8px;
            overflow: hidden;
        }
        
        .items-table th {
            background: {{ $branding->primary_color }};
            color: white;
            padding: 15px;
            text-align: left;
            font-weight: 600;
        }
        
        .items-table td {
            padding: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .items-table tr:last-child td {
            border-bottom: none;
        }
        
        .items-table tr:nth-child(even) {
            background: #f8f9fa;
        }
        
        .item-description {
            font-weight: 600;
            color: #333;
        }
        
        .item-details {
            color: #666;
            font-size: 14px;
            margin-top: 5px;
        }
        
        .amount {
            text-align: right;
            font-weight: 600;
        }
        
        .totals-section {
            margin-top: 30px;
            display: flex;
            justify-content: flex-end;
        }
        
        .totals-table {
            width: 300px;
            border-collapse: collapse;
        }
        
        .totals-table td {
            padding: 10px 15px;
            border-bottom: 1px solid #eee;
        }
        
        .totals-table .total-label {
            text-align: right;
            font-weight: 600;
            color: #333;
        }
        
        .totals-table .total-amount {
            text-align: right;
            font-weight: bold;
            color: {{ $branding->primary_color }};
        }
        
        .totals-table .grand-total {
            background: {{ $branding->primary_color }};
            color: white;
            font-size: 18px;
        }
        
        .totals-table .grand-total .total-label {
            color: white;
        }
        
        .totals-table .grand-total .total-amount {
            color: white;
        }
        
        .footer {
            margin-top: 60px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            text-align: center;
            color: #666;
            font-size: 14px;
        }
        
        .footer-content {
            margin-bottom: 20px;
        }
        
        .payment-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid {{ $branding->primary_color }};
        }
        
        .payment-title {
            font-weight: bold;
            color: {{ $branding->primary_color }};
            margin-bottom: 10px;
        }
        
        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-paid {
            background: #d1fae5;
            color: #065f46;
        }
        
        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }
        
        .status-failed {
            background: #fee2e2;
            color: #991b1b;
        }
        
        @media print {
            .invoice-container {
                padding: 20px;
            }
            
            .header {
                page-break-inside: avoid;
            }
            
            .items-table {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <div class="invoice-container">
        <!-- Header -->
        <div class="header">
            <div class="company-info">
                @if($branding->logo_url)
                    <img src="{{ $branding->logo_url }}" alt="{{ $branding->company_name }}" class="logo">
                @endif
                <div class="company-name">{{ $branding->company_name }}</div>
                <div class="company-details">
                    @if($branding->company_address)
                        {!! $branding->formatted_address !!}<br>
                    @endif
                    @if($branding->company_phone)
                        Phone: {{ $branding->company_phone }}<br>
                    @endif
                    @if($branding->company_email)
                        Email: {{ $branding->company_email }}<br>
                    @endif
                    @if($branding->company_website)
                        Website: {{ $branding->company_website }}
                    @endif
                </div>
            </div>
            
            <div class="invoice-info">
                <div class="invoice-title">INVOICE</div>
                <div class="invoice-number">#{{ $invoice->stripe_invoice_id }}</div>
                <div class="invoice-details">
                    <div class="detail-row">
                        <span class="detail-label">Invoice Date:</span>
                        <span class="detail-value">{{ $invoice->created_at->format('M d, Y') }}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Due Date:</span>
                        <span class="detail-value">{{ $invoice->created_at->addDays(30)->format('M d, Y') }}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Status:</span>
                        <span class="detail-value">
                            <span class="status-badge status-{{ $invoice->status }}">
                                {{ ucfirst($invoice->status) }}
                            </span>
                        </span>
                    </div>
                    @if($invoice->paid_at)
                    <div class="detail-row">
                        <span class="detail-label">Paid Date:</span>
                        <span class="detail-value">{{ $invoice->paid_at->format('M d, Y') }}</span>
                    </div>
                    @endif
                </div>
            </div>
        </div>
        
        <!-- Customer Information -->
        <div class="customer-section">
            <div class="section-title">Bill To</div>
            <div class="customer-info">
                <div class="customer-name">{{ $subscription->user->name ?? $subscription->metadata['customer_name'] ?? 'Customer' }}</div>
                <div class="customer-details">
                    Email: {{ $subscription->user->email ?? $subscription->metadata['customer_email'] ?? 'N/A' }}<br>
                    Customer ID: {{ $subscription->customer_id }}
                </div>
            </div>
        </div>
        
        <!-- Items Table -->
        <table class="items-table">
            <thead>
                <tr>
                    <th>Description</th>
                    <th>Period</th>
                    <th>Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <div class="item-description">{{ $subscription->plan->name }}</div>
                        <div class="item-details">
                            {{ $subscription->plan->interval }} subscription<br>
                            @if($subscription->plan->metadata && isset($subscription->plan->metadata['description']))
                                {{ $subscription->plan->metadata['description'] }}
                            @endif
                        </div>
                    </td>
                    <td>
                        {{ $subscription->current_period_start->format('M d, Y') }} - 
                        {{ $subscription->current_period_end->format('M d, Y') }}
                    </td>
                    <td class="amount">${{ number_format($invoice->amount_cents / 100, 2) }}</td>
                </tr>
            </tbody>
        </table>
        
        <!-- Totals -->
        <div class="totals-section">
            <table class="totals-table">
                <tr>
                    <td class="total-label">Subtotal:</td>
                    <td class="total-amount">${{ number_format($invoice->amount_cents / 100, 2) }}</td>
                </tr>
                <tr>
                    <td class="total-label">Tax:</td>
                    <td class="total-amount">$0.00</td>
                </tr>
                <tr class="grand-total">
                    <td class="total-label">Total:</td>
                    <td class="total-amount">${{ number_format($invoice->amount_cents / 100, 2) }}</td>
                </tr>
            </table>
        </div>
        
        <!-- Payment Information -->
        @if($invoice->status === 'paid')
        <div class="payment-info">
            <div class="payment-title">Payment Information</div>
            <p>Thank you for your payment! This invoice has been paid in full.</p>
            <p><strong>Payment Method:</strong> Credit Card (via Stripe)</p>
            <p><strong>Transaction ID:</strong> {{ $invoice->stripe_invoice_id }}</p>
        </div>
        @else
        <div class="payment-info">
            <div class="payment-title">Payment Instructions</div>
            <p>Please remit payment within 30 days of the invoice date.</p>
            <p>For questions about this invoice, please contact us at {{ $branding->company_email }}</p>
        </div>
        @endif
        
        <!-- Footer -->
        <div class="footer">
            <div class="footer-content">
                @if($branding->invoice_footer)
                    {!! nl2br($branding->invoice_footer) !!}
                @else
                    <p>Thank you for your business!</p>
                    <p>This invoice was generated automatically by {{ $branding->company_name }}.</p>
                @endif
            </div>
            <p><em>Invoice generated on {{ now()->format('M d, Y \a\t g:i A') }}</em></p>
        </div>
    </div>
</body>
</html>
