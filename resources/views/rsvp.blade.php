<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RSVP - {{ $event->name }}</title>
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            max-width: 600px;
            width: 100%;
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #4F46E5 0%, #7C3AED 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
            font-weight: 700;
        }
        
        .header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }
        
        .content {
            padding: 40px 30px;
        }
        
        .event-details {
            background: #f8fafc;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
            border-left: 4px solid #4F46E5;
        }
        
        .event-details h2 {
            color: #1e293b;
            margin-bottom: 20px;
            font-size: 1.5rem;
        }
        
        .detail-row {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            font-size: 1.1rem;
        }
        
        .detail-row strong {
            color: #4F46E5;
            min-width: 80px;
            margin-right: 15px;
        }
        
        .rsvp-section {
            text-align: center;
        }
        
        .rsvp-section h3 {
            color: #1e293b;
            margin-bottom: 30px;
            font-size: 1.3rem;
        }
        
        .rsvp-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
            margin-bottom: 30px;
        }
        
        .rsvp-btn {
            padding: 15px 30px;
            border: none;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            cursor: pointer;
            min-width: 160px;
        }
        
        .rsvp-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .rsvp-btn.going {
            background: #10b981;
            color: white;
        }
        
        .rsvp-btn.going:hover {
            background: #059669;
        }
        
        .rsvp-btn.maybe {
            background: #f59e0b;
            color: white;
        }
        
        .rsvp-btn.maybe:hover {
            background: #d97706;
        }
        
        .rsvp-btn.declined {
            background: #ef4444;
            color: white;
        }
        
        .rsvp-btn.declined:hover {
            background: #dc2626;
        }
        
        .calendar-btn {
            background: #6b7280;
            color: white;
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            display: inline-block;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .calendar-btn:hover {
            background: #4b5563;
            transform: translateY(-1px);
        }
        
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            color: #6b7280;
            font-size: 0.9rem;
        }
        
        .success-message {
            background: #d1fae5;
            color: #065f46;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            border-left: 4px solid #10b981;
        }
        
        .error-message {
            background: #fee2e2;
            color: #991b1b;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            border-left: 4px solid #ef4444;
        }
        
        @media (max-width: 640px) {
            .rsvp-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .rsvp-btn {
                width: 100%;
                max-width: 280px;
            }
            
            .header h1 {
                font-size: 2rem;
            }
            
            .content {
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎉 Event RSVP</h1>
            <p>Please respond to your invitation</p>
        </div>
        
        <div class="content">
            @if(session('success'))
                <div class="success-message">
                    <strong>✅ {{ session('success') }}</strong>
                </div>
            @endif
            
            @if(session('error'))
                <div class="error-message">
                    <strong>❌ {{ session('error') }}</strong>
                </div>
            @endif
            
            <div class="event-details">
                <h2>📅 Event Details</h2>
                <div class="detail-row">
                    <strong>Event:</strong>
                    <span>{{ $event->name }}</span>
                </div>
                <div class="detail-row">
                    <strong>Date:</strong>
                    <span>{{ $event->scheduled_at->format('l, F j, Y') }}</span>
                </div>
                <div class="detail-row">
                    <strong>Time:</strong>
                    <span>{{ $event->scheduled_at->format('g:i A') }}</span>
                </div>
                @if($event->location)
                <div class="detail-row">
                    <strong>Location:</strong>
                    <span>{{ $event->location }}</span>
                </div>
                @endif
                @if($event->description)
                <div class="detail-row">
                    <strong>Description:</strong>
                    <span>{{ $event->description }}</span>
                </div>
                @endif
            </div>
            
            <div class="rsvp-section">
                <h3>Will you be attending?</h3>
                <div class="rsvp-buttons">
                    <a href="{{ url('/api/public/events/' . $event->id . '/rsvp?status=going&contact_id=' . $contactId) }}" 
                       class="rsvp-btn going">
                        ✅ Yes, I'm Coming
                    </a>
                    <a href="{{ url('/api/public/events/' . $event->id . '/rsvp?status=interested&contact_id=' . $contactId) }}" 
                       class="rsvp-btn maybe">
                        🤔 Maybe
                    </a>
                    <a href="{{ url('/api/public/events/' . $event->id . '/rsvp?status=declined&contact_id=' . $contactId) }}" 
                       class="rsvp-btn declined">
                        ❌ No, Can't Make It
                    </a>
                </div>
                
                <div style="margin-top: 20px;">
                    <a href="{{ url('/api/events/' . $event->id . '/calendar') }}" class="calendar-btn">
                        📅 Add to Calendar
                    </a>
                </div>
            </div>
            
            <div class="footer">
                <p>This RSVP page was generated by RC Convergio CRM</p>
                <p>If you have any questions, please contact the event organizer</p>
            </div>
        </div>
    </div>
    
    <script>
        // Add some interactive feedback
        document.querySelectorAll('.rsvp-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                // Show loading state
                const originalText = this.innerHTML;
                this.innerHTML = '⏳ Processing...';
                this.style.opacity = '0.7';
                
                // Reset after a short delay (in case of redirect issues)
                setTimeout(() => {
                    this.innerHTML = originalText;
                    this.style.opacity = '1';
                }, 3000);
            });
        });
    </script>
</body>
</html>







