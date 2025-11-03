<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Verify Your Email Address - RC Convergio</title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, Helvetica, sans-serif; background-color: #f8f9fa;">
    <!-- Outer table for centering -->
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #f8f9fa;">
        <tr>
            <td align="center" style="padding: 20px 0;">
                <!-- Main container table -->
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="600" style="max-width: 600px; background-color: #ffffff; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
                    
                    <!-- Logo and Header -->
                    <tr>
                        <td align="center" style="padding: 40px 40px 30px 40px; border-bottom: 2px solid #e9ecef;">
                            @if($logoUrl)
                                <!-- Use public URL for Gmail compatibility -->
                                <img src="{{ $logoUrl }}" alt="RC Convergio Logo" style="display: block; max-width: 120px; height: auto; margin: 0 auto 15px auto;" />
                            @elseif($logoBase64)
                                <!-- Fallback to base64 if URL not available -->
                                <img src="{{ $logoBase64 }}" alt="RC Convergio Logo" style="display: block; max-width: 120px; height: auto; margin: 0 auto 15px auto;" />
                            @endif
                            <p style="margin: 0; font-size: 28px; font-weight: 700; color: #667eea; text-align: center; letter-spacing: -0.5px;">RC Convergio</p>
                        </td>
                    </tr>
                    
                    <!-- Title -->
                    <tr>
                        <td align="center" style="padding: 30px 40px 10px 40px;">
                            <h1 style="margin: 0; font-size: 32px; font-weight: 700; color: #1a1a1a; text-align: center; line-height: 1.2;">Verify Your Email Address</h1>
                            <p style="margin: 8px 0 0 0; font-size: 16px; color: #6c757d; text-align: center;">One click away from getting started</p>
                        </td>
                    </tr>
                    
                    <!-- Content -->
                    <tr>
                        <td style="padding: 0 40px 30px 40px;">
                            <p style="margin: 0 0 20px 0; font-size: 18px; color: #2c3e50; font-weight: 500;">Hello!</p>
                            
                            <p style="margin: 0 0 25px 0; font-size: 16px; line-height: 1.8; color: #495057;">
                                Thank you for signing up with <strong style="color: #1a1a1a;">RC Convergio</strong>! Please verify your email address by clicking the button below. 
                                This helps us ensure the security of your account and keep your information safe.
                            </p>
                            
                            <!-- Button -->
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                <tr>
                                    <td align="center" style="padding: 30px 0;">
                                        <a href="{{ $url }}" style="display: inline-block; background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%); color: #ffffff; text-decoration: none; padding: 16px 45px; border-radius: 8px; font-size: 18px; font-weight: 600; text-align: center;">Verify Email Address</a>
                                    </td>
                                </tr>
                            </table>
                            
                            <!-- Security Note -->
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin: 25px 0; background: linear-gradient(135deg, #e7f3ff 0%, #d1e7ff 100%); border-left: 4px solid #667eea; border-radius: 6px;">
                                <tr>
                                    <td style="padding: 15px 20px;">
                                        <p style="margin: 0 0 5px 0; font-size: 15px; font-weight: 700; color: #004085;">🔒 Security Information</p>
                                        <p style="margin: 0; font-size: 14px; color: #004085; line-height: 1.6;">
                                            This verification link will expire in <strong>60 minutes</strong> for your security. 
                                            If you did not create an account with RC Convergio, please ignore this email.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                            
                            <!-- Fallback URL -->
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-top: 30px; padding-top: 25px; border-top: 1px solid #e9ecef;">
                                <tr>
                                    <td>
                                        <p style="margin: 0 0 10px 0; font-size: 14px; color: #6c757d; font-weight: 500;">Having trouble with the button?</p>
                                        <p style="margin: 0 0 15px 0; font-size: 14px; color: #6c757d;">
                                            Copy and paste the URL below into your web browser:
                                        </p>
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background: #f8f9fa; border-radius: 6px; border-left: 3px solid #667eea;">
                                            <tr>
                                                <td style="padding: 15px;">
                                                    <p style="margin: 0; font-size: 12px; color: #495057; word-break: break-all; font-family: 'Courier New', monospace;">{{ $url }}</p>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                            
                            <p style="margin: 25px 0 0 0; font-size: 14px; color: #6c757d; line-height: 1.6;">
                                If you did not create an account, no further action is required. You can safely ignore this email.
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td align="center" style="padding: 40px 40px 25px 40px; border-top: 2px solid #e9ecef;">
                            <p style="margin: 0 0 10px 0; font-size: 14px; color: #6c757d;">This email was sent by <span style="color: #667eea; font-weight: 700;">RC Convergio</span></p>
                            <p style="margin: 20px 0 10px 0; font-size: 16px; font-weight: 600; color: #2c3e50;">
                                Best regards,<br>
                                <span style="color: #667eea; font-weight: 700;">The RC Convergio Team</span>
                            </p>
                            <p style="margin: 15px 0 0 0; font-size: 12px; color: #999999;">
                                © {{ date('Y') }} RC Convergio. All rights reserved.
                            </p>
                        </td>
                    </tr>
                    
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
