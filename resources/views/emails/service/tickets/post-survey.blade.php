<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>How was your support experience?</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .container {
            background-color: #ffffff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .header h1 {
            color: #007bff;
            margin: 0;
            font-size: 24px;
        }
        .content {
            margin-bottom: 30px;
        }
        .ticket-info {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            border-left: 4px solid #007bff;
        }
        .survey-button {
            text-align: center;
            margin: 30px 0;
        }
        .survey-button a {
            background-color: #007bff;
            color: white;
            padding: 15px 30px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            display: inline-block;
            transition: background-color 0.3s;
        }
        .survey-button a:hover {
            background-color: #0056b3;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            color: #666;
            font-size: 14px;
        }
        .ticket-details {
            font-size: 14px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Thank you for contacting us!</h1>
        </div>
        
        <div class="content">
            <p>Hi {{ $ticket->contact->name ?? 'there' }},</p>
            
            <p>We hope we were able to resolve your issue with:</p>
            
            <div class="ticket-info">
                <strong>Ticket Subject:</strong> {{ $ticket->subject }}<br>
                <div class="ticket-details">
                    <strong>Ticket ID:</strong> #{{ $ticket->id }}<br>
                    <strong>Status:</strong> {{ ucfirst($ticket->status) }}<br>
                    <strong>Closed:</strong> {{ $ticket->updated_at->format('M d, Y \a\t g:i A') }}
                </div>
            </div>
            
            <p>We'd love to hear about your experience with our support team. Your feedback helps us improve our service and ensure we're meeting your needs.</p>
            
            <p>Could you take a quick moment to rate your support experience?</p>
        </div>
        
        <div class="survey-button">
            <a href="{{ $surveyUrl }}">Rate Your Experience</a>
        </div>
        
        <div class="content">
            <p>The survey will only take 2-3 minutes to complete, and your feedback is invaluable to us.</p>
            
            <p>If you have any additional questions or concerns, please don't hesitate to reach out to our support team.</p>
        </div>
        
        <div class="footer">
            <p>Best regards,<br>
            <strong>Support Team</strong></p>
            
            <p><small>This survey is related to your support ticket #{{ $ticket->id }}. If you have any questions about this survey, please contact our support team.</small></p>
        </div>
    </div>
</body>
</html>
