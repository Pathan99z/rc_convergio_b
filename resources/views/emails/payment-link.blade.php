<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Link - {{ $quote->quote_number ?? 'Invoice' }}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f8f9fa;
        }
        .container {
            background: white;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #e9ecef;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .logo {
            font-size: 24px;
            font-weight: bold;
            color: #007bff;
            margin-bottom: 10px;
        }
        .title {
            font-size: 28px;
            font-weight: 600;
            color: #2c3e50;
            margin: 0;
        }
        .subtitle {
            color: #6c757d;
            font-size: 16px;
            margin-top: 5px;
        }
        .content {
            margin-bottom: 30px;
        }
        .greeting {
            font-size: 18px;
            margin-bottom: 20px;
        }
        .message {
            font-size: 16px;
            margin-bottom: 25px;
            line-height: 1.7;
        }
        .quote-details {
            background: #f8f9fa;
            border-radius: 6px;
            padding: 20px;
            margin: 20px 0;
        }
        .quote-number {
            font-size: 18px;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 10px;
        }
        .amount {
            font-size: 24px;
            font-weight: bold;
            color: #28a745;
            margin-bottom: 5px;
        }
        .currency {
            font-size: 14px;
            color: #6c757d;
        }
        .payment-button {
            display: inline-block;
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            text-decoration: none;
            padding: 15px 30px;
            border-radius: 6px;
            font-size: 18px;
            font-weight: 600;
            text-align: center;
            margin: 20px 0;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0,123,255,0.3);
        }
        .payment-button:hover {
            background: linear-gradient(135deg, #0056b3, #004085);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,123,255,0.4);
        }
        .security-note {
            background: #e7f3ff;
            border-left: 4px solid #007bff;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .security-note h4 {
            margin: 0 0 10px 0;
            color: #0056b3;
            font-size: 16px;
        }
        .security-note p {
            margin: 0;
            font-size: 14px;
            color: #495057;
        }
        .footer {
            border-top: 1px solid #e9ecef;
            padding-top: 20px;
            margin-top: 30px;
            text-align: center;
            color: #6c757d;
            font-size: 14px;
        }
        .contact-info {
            margin-top: 15px;
        }
        .test-mode {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 10px;
            border-radius: 4px;
            margin: 15px 0;
            text-align: center;
            font-weight: 600;
        }
        @media (max-width: 600px) {
            body {
                padding: 10px;
            }
            .container {
                padding: 20px;
            }
            .title {
                font-size: 24px;
            }
            .amount {
                font-size: 20px;
            }
            .payment-button {
                padding: 12px 25px;
                font-size: 16px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">{{ config('app.name', 'RC Convergio') }}</div>
            <h1 class="title">Payment Request</h1>
            <p class="subtitle">Secure payment link for your invoice</p>
        </div>

        <div class="content">
            <div class="greeting">
                Hello {{ $customerName ?? 'Valued Customer' }},
            </div>

            <div class="message">
                @if($quote)
                    Thank you for your business! We've prepared your payment link for <strong>Quote {{ $quote->quote_number }}</strong>.
                @else
                    Thank you for your business! We've prepared your payment link for your invoice.
                @endif
                
                Please click the button below to complete your payment securely.
            </div>

            @if($isTestMode)
                <div class="test-mode">
                    🧪 TEST MODE - This is a test payment link
                </div>
            @endif

            <div class="quote-details">
                @if($quote)
                    <div class="quote-number">Quote #{{ $quote->quote_number }}</div>
                @endif
                <div class="amount">${{ number_format($amount, 2) }}</div>
                <div class="currency">{{ $currency ?? 'USD' }}</div>
            </div>

            <div style="text-align: center;">
                <a href="{{ $paymentUrl }}" class="payment-button">
                    💳 Pay Now Securely
                </a>
            </div>

            <div class="security-note">
                <h4>🔒 Secure Payment</h4>
                <p>
                    This payment link is secured with industry-standard encryption. 
                    Your payment information is processed securely and never stored on our servers.
                </p>
            </div>

            @if($expiresAt)
                <div style="text-align: center; color: #6c757d; font-size: 14px; margin: 20px 0;">
                    ⏰ This payment link expires on {{ $expiresAt->format('M j, Y \a\t g:i A') }}
                </div>
            @endif

            <div style="margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 6px;">
                <h4 style="margin-top: 0; color: #2c3e50;">Payment Instructions:</h4>
                <ol style="margin: 0; padding-left: 20px;">
                    <li>Click the "Pay Now Securely" button above</li>
                    <li>Enter your payment details on the secure checkout page</li>
                    <li>Review your order and complete the payment</li>
                    <li>You'll receive a confirmation email once payment is processed</li>
                </ol>
            </div>
        </div>

        <div class="footer">
            <p>
                If you have any questions about this payment or need assistance, 
                please don't hesitate to contact us.
            </p>
            
            <div class="contact-info">
                <p><strong>{{ config('app.name', 'RC Convergio') }}</strong></p>
                @if(config('mail.from.address'))
                    <p>Email: {{ config('mail.from.address') }}</p>
                @endif
                @if(config('app.url'))
                    <p>Website: {{ config('app.url') }}</p>
                @endif
            </div>

            <p style="margin-top: 20px; font-size: 12px; color: #adb5bd;">
                This is an automated message. Please do not reply to this email.
            </p>
        </div>
    </div>
</body>
</html>
