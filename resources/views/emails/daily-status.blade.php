<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Status Notification</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background-color: white;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }
        .header h1 {
            color: #2563eb;
            margin: 0;
            font-size: 24px;
        }
        .status-badge {
            display: inline-block;
            background-color: #10b981;
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            margin-top: 10px;
        }
        .content {
            margin: 30px 0;
        }
        .summary-box {
            background-color: #f9fafb;
            border-left: 4px solid #2563eb;
            padding: 15px;
            margin: 20px 0;
        }
        .summary-box h3 {
            margin-top: 0;
            color: #1f2937;
            font-size: 16px;
        }
        .summary-stats {
            display: flex;
            justify-content: space-around;
            margin: 20px 0;
        }
        .stat-item {
            text-align: center;
        }
        .stat-value {
            font-size: 28px;
            font-weight: bold;
            color: #2563eb;
        }
        .stat-label {
            font-size: 14px;
            color: #6b7280;
            margin-top: 5px;
        }
        .reminders-section {
            margin: 30px 0;
        }
        .reminders-section h3 {
            color: #1f2937;
            font-size: 18px;
            margin-bottom: 15px;
        }
        .reminder-item {
            background-color: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 10px;
        }
        .reminder-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }
        .reminder-name {
            font-weight: 600;
            color: #1f2937;
        }
        .reminder-amount {
            color: #2563eb;
            font-weight: 600;
        }
        .reminder-date {
            color: #6b7280;
            font-size: 14px;
        }
        .days-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 10px;
        }
        .days-urgent {
            background-color: #fee2e2;
            color: #dc2626;
        }
        .days-soon {
            background-color: #fef3c7;
            color: #d97706;
        }
        .days-normal {
            background-color: #dbeafe;
            color: #2563eb;
        }
        .no-reminders {
            text-align: center;
            padding: 30px;
            color: #6b7280;
            font-style: italic;
        }
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px solid #f0f0f0;
            text-align: center;
            color: #6b7280;
            font-size: 12px;
        }
        .settings-link {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 20px;
            background-color: #2563eb;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
        }
        .settings-link:hover {
            background-color: #1d4ed8;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Remindy</h1>
            <div class="status-badge">✓ Notification Service Active</div>
        </div>

        <div class="content">
            <p>Hi {{ $user->name }},</p>
            
            <p>This is your daily status notification confirming that your Remindy notification service is working properly.</p>

            <div class="summary-box">
                <h3>Your Account Summary</h3>
                <div class="summary-stats">
                    <div class="stat-item">
                        <div class="stat-value">{{ $activeSubscriptions }}</div>
                        <div class="stat-label">Active Subscriptions</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value">{{ count($upcomingReminders) }}</div>
                        <div class="stat-label">Upcoming Reminders</div>
                    </div>
                </div>
            </div>

            @if(count($upcomingReminders) > 0)
                <div class="reminders-section">
                    <h3>Upcoming Reminders (Next 7 Days)</h3>
                    @foreach($upcomingReminders as $reminder)
                        <div class="reminder-item">
                            <div class="reminder-header">
                                <span class="reminder-name">{{ $reminder['name'] }}</span>
                                <span class="reminder-amount">{{ $reminder['currency'] }} {{ number_format($reminder['amount'], 2) }}</span>
                            </div>
                            <div class="reminder-date">
                                Due: {{ $reminder['due_date'] }}
                                @if($reminder['days_until'] <= 1)
                                    <span class="days-badge days-urgent">{{ $reminder['days_until'] }} day{{ $reminder['days_until'] != 1 ? 's' : '' }}</span>
                                @elseif($reminder['days_until'] <= 3)
                                    <span class="days-badge days-soon">{{ $reminder['days_until'] }} days</span>
                                @else
                                    <span class="days-badge days-normal">{{ $reminder['days_until'] }} days</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="no-reminders">
                    <p>No upcoming reminders in the next 7 days.</p>
                </div>
            @endif

            <div style="text-align: center; margin-top: 30px;">
                <a href="{{ url('/settings/notifications') }}" class="settings-link">
                    Manage Notification Settings
                </a>
            </div>
        </div>

        <div class="footer">
            <p>This daily status email confirms your notification service is active and working.</p>
            <p>You're receiving this because you enabled daily status notifications.</p>
            <p>To stop receiving these emails, disable daily notifications in your settings.</p>
            <p style="margin-top: 15px; color: #9ca3af;">
                Tracking ID: {{ $trackingId }}
            </p>
        </div>
    </div>
</body>
</html>