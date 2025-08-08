<x-mail::message>
# Test Email Notification

Hello {{ $userName }},

This is a test email from your Remindy notification settings. If you're receiving this email, your SMTP configuration is working correctly!

## Configuration Details
- **Email Address:** {{ $emailAddress }}
- **SMTP Host:** {{ $smtpHost }}
- **Status:** ✅ Working

You can now receive subscription reminders at this email address.

<x-mail::button :url="config('app.url') . '/settings/notifications'">
View Notification Settings
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>