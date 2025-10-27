<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $subject }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .ticket-info {
            background-color: #e9ecef;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .message-content {
            background-color: #ffffff;
            border: 1px solid #dee2e6;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .footer {
            font-size: 12px;
            color: #6c757d;
            text-align: center;
            margin-top: 30px;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background-color: #28a745;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <h2>Support Ticket Closed</h2>
    </div>

    <div class="ticket-info">
        <h3>Ticket Details</h3>
        <p><strong>Ticket #{{ $ticket->id }}:</strong> {{ $ticket->subject }}</p>
        <p><strong>Status:</strong> {{ ucfirst($ticket->status) }}</p>
        <p><strong>Priority:</strong> {{ ucfirst($ticket->priority) }}</p>
        @if($ticket->resolved_at)
        <p><strong>Resolved:</strong> {{ $ticket->resolved_at->format('M j, Y g:i A') }}</p>
        @endif
    </div>

    <div class="message-content">
        <h4>Your ticket has been closed and marked as resolved.</h4>
        <p>Thank you for contacting our support team. We hope we were able to resolve your issue satisfactorily.</p>
        
        @if($ticket->description)
        <h5>Original Issue:</h5>
        <div style="white-space: pre-wrap;">{{ $ticket->description }}</div>
        @endif
    </div>

    <div style="text-align: center;">
        <a href="{{ config('app.url') }}/service/tickets/{{ $ticket->id }}" class="btn">View Closed Ticket</a>
    </div>

    <div class="footer">
        <p>If you have any additional questions or concerns, please feel free to create a new support ticket.</p>
        <p>Thank you for using our support services!</p>
    </div>
</body>
</html>
