<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Mail\TestNotificationMail;
use App\Services\UserMailer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    /**
     * Display the notification settings page.
     */
    public function index(): Response
    {
        $user = Auth::user();
        
        $availableIntervals = [
            ['value' => 30, 'label' => '30 days before'],
            ['value' => 15, 'label' => '15 days before'],
            ['value' => 7, 'label' => '1 week before'],
            ['value' => 3, 'label' => '3 days before'],
            ['value' => 2, 'label' => '2 days before'],
            ['value' => 1, 'label' => '1 day before'],
        ];

        return Inertia::render('settings/notifications', [
            'notificationSettings' => [
                'notification_time_utc' => $user->notification_time_utc,
                'default_email_enabled' => $user->default_email_enabled,
                'default_webhook_enabled' => $user->default_webhook_enabled,
                'default_reminder_intervals' => $user->getDefaultReminderIntervalsWithFallback(),
                'notification_email' => $user->notification_email ?? $user->email,
                'webhook_url' => $user->webhook_url,
                'webhook_headers' => $user->webhook_headers,
                // SMTP settings (always required)
                'smtp_host' => $user->smtp_host,
                'smtp_port' => $user->smtp_port,
                'smtp_username' => $user->smtp_username,
                'smtp_encryption' => $user->smtp_encryption,
                'smtp_from_address' => $user->smtp_from_address,
                'smtp_from_name' => $user->smtp_from_name,
            ],
            'availableIntervals' => $availableIntervals,
        ]);
    }

    /**
     * Update the notification settings.
     */
    public function update(Request $request)
    {
        $user = Auth::user();

        // Build validation rules dynamically based on email_enabled state
        $rules = [
            'notification_time_utc' => 'required|date_format:H:i:s',
            'default_email_enabled' => 'boolean',
            'default_webhook_enabled' => 'boolean',
            'default_reminder_intervals' => 'array',
            'default_reminder_intervals.*' => 'integer|in:1,2,3,7,15,30',
            'webhook_url' => 'nullable|url',
            'webhook_headers' => 'nullable|array',
        ];

        // If email is enabled, email address and SMTP settings are required
        if ($request->input('default_email_enabled')) {
            $rules['notification_email'] = 'required|email';
            // SMTP settings are always required when email is enabled
            $rules['smtp_host'] = 'required|string';
            $rules['smtp_port'] = 'required|integer|between:1,65535';
            $rules['smtp_username'] = 'required|string';
            $rules['smtp_password'] = 'nullable|string'; // nullable because we don't update if not provided
            $rules['smtp_encryption'] = ['nullable', Rule::in(['tls', 'ssl', 'starttls', 'none', null])];
            $rules['smtp_from_address'] = 'required|email';
            $rules['smtp_from_name'] = 'required|string';
        } else {
            $rules['notification_email'] = 'nullable|email';
            $rules['smtp_host'] = 'nullable|string';
            $rules['smtp_port'] = 'nullable|integer';
            $rules['smtp_username'] = 'nullable|string';
            $rules['smtp_password'] = 'nullable|string';
            $rules['smtp_encryption'] = 'nullable|string';
            $rules['smtp_from_address'] = 'nullable|email';
            $rules['smtp_from_name'] = 'nullable|string';
        }

        $validated = $request->validate($rules);

        // Handle password update (don't update if empty)
        if (empty($validated['smtp_password'])) {
            unset($validated['smtp_password']);
        }

        // Convert 'none' encryption to null
        if (isset($validated['smtp_encryption']) && $validated['smtp_encryption'] === 'none') {
            $validated['smtp_encryption'] = null;
        }

        $user->update($validated);

        return back()->with('success', 'Notification settings updated successfully.');
    }

    /**
     * Send a test email to verify SMTP settings.
     */
    public function testEmail(Request $request)
    {
        $user = Auth::user();

        // Validate the request
        $validated = $request->validate([
            'notification_email' => 'required|email',
            'smtp_host' => 'required|string',
            'smtp_port' => 'required|integer',
            'smtp_username' => 'required|string',
            'smtp_password' => 'nullable|string',
            'smtp_encryption' => 'nullable|string',
            'smtp_from_address' => 'required|email',
            'smtp_from_name' => 'required|string',
        ]);

        // Create a temporary user instance with the form values for testing
        $testUser = clone $user;
        $testUser->notification_email = $validated['notification_email'];
        $testUser->smtp_host = $validated['smtp_host'];
        $testUser->smtp_port = $validated['smtp_port'];
        $testUser->smtp_username = $validated['smtp_username'];
        // Use existing password if not provided in form
        $testUser->smtp_password = !empty($validated['smtp_password']) 
            ? $validated['smtp_password'] 
            : $user->smtp_password;
        $testUser->smtp_encryption = $validated['smtp_encryption'] === 'none' ? null : $validated['smtp_encryption'];
        $testUser->smtp_from_address = $validated['smtp_from_address'];
        $testUser->smtp_from_name = $validated['smtp_from_name'];

        if (!$testUser->smtp_password) {
            return back()->withErrors(['email' => 'SMTP password is required for testing.']);
        }

        try {
            // Send test email using the form's SMTP settings
            UserMailer::send(
                $testUser,
                new TestNotificationMail($testUser),
                $validated['notification_email']
            );

            return back()->with('success', 'Test email sent successfully! Check your inbox.');
        } catch (\Exception $e) {
            return back()->withErrors(['email' => 'Failed to send test email: ' . $e->getMessage()]);
        }
    }
}