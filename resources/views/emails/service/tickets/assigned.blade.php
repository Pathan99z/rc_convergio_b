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
            background-color: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <h2>New Support Ticket Assigned to You</h2>
    </div>

    <div class="ticket-info">
        <h3>Ticket Details</h3>
        <p><strong>Ticket #{{ $ticket->id }}:</strong> {{ $ticket->subject }}</p>
        <p><strong>Status:</strong> {{ ucfirst($ticket->status) }}</p>
        <p><strong>Priority:</strong> {{ ucfirst($ticket->priority) }}</p>
        @if($ticket->sla_due_at)
        <p><strong>SLA Due:</strong> {{ $ticket->sla_due_at->format('M j, Y g:i A') }}</p>
        @endif
        @if($contact)
        <p><strong>Customer:</strong> {{ $contact->first_name }} {{ $contact->last_name }} ({{ $contact->email }})</p>
        @endif
    </div>

    <div class="message-content">
        <h4>Issue Description:</h4>
        <div style="white-space: pre-wrap;">{{ $ticket->description }}</div>
    </div>

    <div style="text-align: center;">
        <a href="{{ config('app.url') }}/service/tickets/{{ $ticket->id }}" class="btn">View Ticket</a>
    </div>

    <div class="footer">
        <p>This ticket has been assigned to you. Please review and respond as soon as possible.</p>
    </div>
</body>
</html>
