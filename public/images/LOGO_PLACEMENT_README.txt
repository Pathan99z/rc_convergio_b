================================================================================
RC CONVERGIO LOGO PLACEMENT INSTRUCTIONS
================================================================================

EXACT LOCATION FOR YOUR LOGO FILE:
===================================

File Path: public/images/rc-convergio-logo.png
Full Path: C:\xampp\htdocs\rc_convergio_s\public\images\rc-convergio-logo.png

SUPPORTED FILE FORMATS:
=======================
- PNG (Recommended - supports transparency)
- JPG/JPEG
- SVG

RECOMMENDED SPECIFICATIONS:
===========================
- Size: 60x60px to 200x200px (scaled automatically)
- Format: PNG with transparent background
- Aspect Ratio: Square (1:1)
- Colors: Gradient from blue (#3b82f6) to magenta/pink (#ec4899)

HOW IT WORKS:
=============
1. If logo file exists at the path above → Your actual logo will be displayed
2. If logo file does NOT exist → A CSS-based fallback logo matching your brand design will be shown

WHERE THIS LOGO IS USED:
========================
1. Email Verification Email Template
   - File: resources/views/emails/verify-email.blade.php
   - When: Users receive email verification email after registration
   
2. Email Verification Result Page
   - File: resources/views/emails/verify-email-result.blade.php
   - When: Users click verification link (success/failure page)

CODE REFERENCES:
===============
- Logo URL Check: app/Notifications/VerifyEmailNotification.php (getLogoUrl method)
- Logo URL Check: app/Http/Controllers/Api/AuthController.php (renderVerificationPage method)
- Logo Display: resources/views/emails/verify-email.blade.php
- Logo Display: resources/views/emails/verify-email-result.blade.php

IMPORTANT NOTES:
===============
- The logo will automatically appear in emails once you place the file
- No code changes needed after placing the logo file
- The system will automatically detect and use your logo
- Make sure the file is named exactly: rc-convergio-logo.png
- File permissions should allow web server to read the file

TESTING:
========
After placing your logo:
1. Register a new user
2. Check the verification email - should show your logo
3. Click verification link - success page should show your logo

================================================================================
PRODUCTION READY - NO API OR FUNCTIONALITY CHANGES
================================================================================

