<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Subscription Checkout Link</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f8fafc;
        }
        .email-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #3b82f6, #8b5cf6);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .logo {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .content {
            padding: 30px;
        }
        .demo-badge {
            background: #fef3c7;
            border: 1px solid #f59e0b;
            color: #92400e;
            padding: 10px 15px;
            border-radius: 6px;
            text-align: center;
            margin-bottom: 20px;
            font-weight: 600;
        }
        .plan-details {
            background: #f9fafb;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .plan-name {
            font-size: 20px;
            font-weight: bold;
            color: #1f2937;
            margin-bottom: 10px;
        }
        .plan-price {
            font-size: 24px;
            color: #3b82f6;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .plan-interval {
            color: #6b7280;
        }
        .trial-info {
            background: #d1fae5;
            border: 1px solid #10b981;
            color: #065f46;
            padding: 10px 15px;
            border-radius: 6px;
            margin: 15px 0;
            text-align: center;
        }
        .cta-button {
            display: inline-block;
            background: linear-gradient(135deg, #3b82f6, #8b5cf6);
            color: white;
            text-decoration: none;
            padding: 15px 30px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            text-align: center;
            margin: 20px 0;
            transition: transform 0.2s;
        }
        .cta-button:hover {
            transform: translateY(-2px);
        }
        .footer {
            background: #f9fafb;
            padding: 20px;
            text-align: center;
            color: #6b7280;
            font-size: 14px;
        }
        .contact-info {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #e5e7eb;
        }
        .contact-info p {
            margin: 5px 0;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <div class="logo">{{ $company_name }}</div>
            <h1>Your Subscription Checkout Link</h1>
        </div>
        
        <div class="content">
            <div class="demo-badge">
                🧪 DEMO MODE - This is a demonstration subscription checkout
            </div>
            
            <p>Hello {{ $customer_name }},</p>
            
            <p>Thank you for your interest in our subscription service! We've prepared a personalized checkout link for you to complete your subscription.</p>
            
            <div class="plan-details">
                <div class="plan-name">{{ $plan_name }}</div>
                <div class="plan-price">${{ $amount }} {{ strtoupper($currency) }}</div>
                <div class="plan-interval">per {{ ucfirst($interval) }}</div>
            </div>
            
            @if($trial_days > 0)
            <div class="trial-info">
                🎉 {{ $trial_days }} days free trial included!
            </div>
            @endif
            
            <p>Click the button below to complete your subscription setup:</p>
            
            <div style="text-align: center;">
                <a href="{{ $checkout_url }}" class="cta-button">
                    Complete Subscription Setup
                </a>
            </div>
            
            <p><strong>Important:</strong> This is a demo checkout link for testing purposes. No real payment will be processed.</p>
            
            <p>If you have any questions or need assistance, please don't hesitate to contact us.</p>
            
            <p>Best regards,<br>
            The {{ $company_name }} Team</p>
        </div>
        
        <div class="footer">
            <div class="contact-info">
                @if($company_email)
                <p><strong>Email:</strong> {{ $company_email }}</p>
                @endif
                @if($company_phone)
                <p><strong>Phone:</strong> {{ $company_phone }}</p>
                @endif
                @if($company_website)
                <p><strong>Website:</strong> <a href="{{ $company_website }}" style="color: #3b82f6;">{{ $company_website }}</a></p>
                @endif
            </div>
            <p style="margin-top: 15px; font-size: 12px; color: #9ca3af;">
                This email was sent to {{ $customer_email }}. If you did not request this subscription, please ignore this email.
            </p>
        </div>
    </div>
</body>
</html>
