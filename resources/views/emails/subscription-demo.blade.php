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
            padding: 36px 32px;
        }
        .content p {
            margin-bottom: 16px;
            color: #374151;
            font-size: 15px;
            line-height: 1.7;
        }
        .demo-badge {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            border: 2px solid #f59e0b;
            color: #92400e;
            padding: 12px 18px;
            border-radius: 8px;
            text-align: center;
            margin-bottom: 24px;
            font-weight: 600;
            font-size: 14px;
            box-shadow: 0 2px 4px rgba(245, 158, 11, 0.1);
        }
        .plan-details {
            background: linear-gradient(135deg, #f9fafb 0%, #ffffff 100%);
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            padding: 24px;
            margin: 24px 0;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        .plan-name {
            font-size: 22px;
            font-weight: 700;
            color: #111827;
            margin-bottom: 12px;
            letter-spacing: -0.3px;
        }
        .plan-price {
            font-size: 28px;
            color: #3b82f6;
            font-weight: 700;
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }
        .plan-interval {
            color: #4b5563;
            font-size: 16px;
            font-weight: 600;
            margin-top: 4px;
        }
        .trial-info {
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
            border: 2px solid #10b981;
            color: #065f46;
            padding: 12px 18px;
            border-radius: 8px;
            margin: 20px 0;
            text-align: center;
            font-weight: 600;
            font-size: 15px;
            box-shadow: 0 2px 4px rgba(16, 185, 129, 0.1);
        }
        .cta-button {
            display: inline-block;
            background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 100%);
            color: white;
            text-decoration: none;
            padding: 16px 40px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 16px;
            text-align: center;
            margin: 24px 0;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
            letter-spacing: 0.3px;
        }
        .cta-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(59, 130, 246, 0.5);
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
                ⚠️ DEMO MODE - This is a demonstration subscription checkout
            </div>
            
            <p>Hello {{ $customer_name }},</p>
            
            <p>Thank you for your interest in our subscription service. We have prepared a personalized checkout link for you to complete your subscription.</p>
            
            <div class="plan-details">
                <div class="plan-name">{{ $plan_name }}</div>
                <div class="plan-price">${{ $amount }} {{ strtoupper($currency) }}</div>
                <div class="plan-interval">
                    @php
                        // Normalize interval value to handle typos and ensure professional display
                        $intervalLower = strtolower(trim($interval ?? 'month'));
                        $intervalMap = [
                            'month' => 'month',
                            'monthly' => 'month',
                            'montlu' => 'month', // Fix common typo
                            'montly' => 'month', // Fix common typo
                            'montlh' => 'month', // Fix common typo
                            'year' => 'year',
                            'yearly' => 'year',
                            'annually' => 'year',
                            'week' => 'week',
                            'weekly' => 'week',
                            'day' => 'day',
                            'daily' => 'day',
                        ];
                        $normalizedInterval = $intervalMap[$intervalLower] ?? 'month';
                        $displayInterval = ucfirst($normalizedInterval);
                        if ($normalizedInterval === 'month') {
                            $displayInterval = 'Month';
                        } elseif ($normalizedInterval === 'year') {
                            $displayInterval = 'Year';
                        } elseif ($normalizedInterval === 'week') {
                            $displayInterval = 'Week';
                        } elseif ($normalizedInterval === 'day') {
                            $displayInterval = 'Day';
                        }
                    @endphp
                    Per {{ $displayInterval }}
                </div>
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
            
            <div style="background: linear-gradient(135deg, #fff5f5 0%, #ffe5e5 100%); border-left: 4px solid #ef4444; padding: 14px 18px; border-radius: 8px; margin: 24px 0; color: #991b1b; box-shadow: 0 2px 4px rgba(239, 68, 68, 0.1);">
                <strong style="color: #dc2626;">Important:</strong> This is a demo checkout link for testing purposes. No real payment will be processed.
            </div>
            
            <p>If you have any questions or need assistance, please do not hesitate to contact us.</p>
            
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
