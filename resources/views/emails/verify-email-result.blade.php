<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $success ? 'Email Verified Successfully' : 'Verification Failed' }} - RC Convergio</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            line-height: 1.6;
            color: #333;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 16px;
            padding: 50px 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 550px;
            width: 100%;
            text-align: center;
        }
        .logo-container {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            margin-bottom: 30px;
        }
        .logo-img {
            max-width: 70px;
            height: auto;
            display: block;
        }
        .logo-text {
            font-size: 32px;
            font-weight: 700;
            color: #2c3e50;
            letter-spacing: -0.5px;
            margin: 0;
        }
        .icon-container {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            margin: 0 auto 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 50px;
        }
        .success-icon {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }
        .error-icon {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
        }
        .title {
            font-size: 32px;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 15px;
            line-height: 1.2;
        }
        .message {
            font-size: 18px;
            color: #495057;
            margin-bottom: 40px;
            line-height: 1.7;
        }
        .button-container {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .btn {
            display: inline-block;
            padding: 16px 35px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }
        .btn-secondary {
            background: #f8f9fa;
            color: #495057;
            border: 2px solid #dee2e6;
        }
        .btn-secondary:hover {
            background: #e9ecef;
            border-color: #adb5bd;
        }
        .footer {
            margin-top: 40px;
            padding-top: 25px;
            border-top: 2px solid #e9ecef;
            font-size: 14px;
            color: #6c757d;
        }
        .footer-company {
            color: #667eea;
            font-weight: 700;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo-container">
            @if($logoUrl)
                <img src="{{ $logoUrl }}" alt="RC Convergio Logo" class="logo-img" />
            @else
                <!-- Fallback: Stylized RC logo matching your brand design -->
                <div style="position: relative; width: 70px; height: 70px; display: flex; align-items: center; justify-content: center;">
                    <!-- Crescent border (incomplete circle) -->
                    <svg width="70" height="70" style="position: absolute; top: 0; left: 0;">
                        <path d="M 35,5 A 30,30 0 0,1 65,35 A 30,30 0 0,1 35,65" 
                              fill="none" 
                              stroke="url(#logoGradientResult)" 
                              stroke-width="3.5" 
                              stroke-linecap="round"/>
                        <defs>
                            <linearGradient id="logoGradientResult" x1="0%" y1="0%" x2="100%" y2="0%">
                                <stop offset="0%" style="stop-color:#3b82f6;stop-opacity:1" />
                                <stop offset="100%" style="stop-color:#ec4899;stop-opacity:1" />
                            </linearGradient>
                        </defs>
                    </svg>
                    <!-- RC text with gradient -->
                    <div style="position: relative; font-family: 'Arial', sans-serif; font-weight: 700; font-size: 24px; background: linear-gradient(135deg, #3b82f6 0%, #ec4899 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; letter-spacing: -1px;">rc</div>
                </div>
            @endif
            <span class="logo-text">RC Convergio</span>
        </div>
        
        <div class="icon-container {{ $success ? 'success-icon' : 'error-icon' }}">
            {{ $success ? '✓' : '✕' }}
        </div>
        
        <h1 class="title">
            {{ $success ? 'Email Verified Successfully!' : 'Verification Failed' }}
        </h1>
        
        <p class="message">
            {{ $message }}
        </p>
        
        @if($success)
        <div class="button-container">
            <a href="{{ $frontendUrl }}/login" class="btn btn-primary">Go to Login</a>
            <a href="{{ $frontendUrl }}" class="btn btn-secondary">Visit Homepage</a>
        </div>
        @else
        <div class="button-container">
            <a href="{{ $frontendUrl }}/register" class="btn btn-primary">Register Again</a>
            <a href="{{ $frontendUrl }}" class="btn btn-secondary">Visit Homepage</a>
        </div>
        @endif
        
        <div class="footer">
            <p>© {{ date('Y') }} <span class="footer-company">RC Convergio</span>. All rights reserved.</p>
        </div>
    </div>
</body>
</html>

