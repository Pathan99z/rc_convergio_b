<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Event Registration Confirmed</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #d4edda; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .event-details { background: #fff; border: 1px solid #ddd; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .button { display: inline-block; background: #28a745; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; margin: 10px 5px; }
        .button:hover { background: #1e7e34; }
        .footer { font-size: 12px; color: #666; margin-top: 30px; }
        .status { background: #e7f3ff; padding: 10px; border-radius: 5px; margin: 10px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Registration Confirmed! ✅</h1>
            <p>Hello {{ $contact->first_name ?? $contact->name }},</p>
        </div>

        <div class="status">
            <strong>Your registration for "{{ $event->name }}" has been confirmed!</strong>
        </div>

        <div class="event-details">
            <h2>{{ $event->name }}</h2>
            <p><strong>Date & Time:</strong> {{ $event->scheduled_at->format('l, F j, Y \a\t g:i A') }}</p>
            @if($event->location)
                <p><strong>Location:</strong> {{ $event->location }}</p>
            @endif
            @if($event->description)
                <p><strong>Description:</strong></p>
                <p>{{ $event->description }}</p>
            @endif
            <p><strong>Your RSVP Status:</strong> {{ ucfirst($attendee->rsvp_status) }}</p>
        </div>

        <div style="text-align: center;">
            <a href="{{ $publicUrl }}" class="button">View Event Details</a>
            <a href="{{ $calendarUrl }}" class="button">Add to Calendar</a>
        </div>

        <div style="background: #fff3cd; padding: 15px; border-radius: 5px; margin: 20px 0;">
            <h3>📅 Reminder</h3>
            <p>We'll send you a reminder 24 hours before the event. Make sure to add this event to your calendar!</p>
        </div>

        <div class="footer">
            <p>This confirmation was sent to {{ $contact->email }}. If you need to make changes to your registration, please contact us.</p>
            <p>© {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>








