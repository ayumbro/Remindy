@component('mail::message')
# Bill Reminder: {{ $subscription->name }}

Hello {{ $user->name }},

@if($daysBefore > 0)
This is a reminder that your **{{ $subscription->name }}** subscription bill is due in **{{ $daysBefore }} day{{ $daysBefore > 1 ? 's' : '' }}** on **{{ $formattedDueDate }}**.
@else
This is a reminder that your **{{ $subscription->name }}** subscription bill is due **today** ({{ $formattedDueDate }}).
@endif

## Subscription Details

**Service:** {{ $subscription->name }}
@if($subscription->description)
**Description:** {{ $subscription->description }}
@endif
**Amount:** {{ $subscription->currency->symbol }}{{ number_format($subscription->price, 2) }}
**Due Date:** {{ $formattedDueDate }}
@if($subscription->payment_method)
**Payment Method:** {{ $subscription->payment_method->name }}
@endif

@component('mail::panel')
{{ $reminderText }}
@endcomponent

@if($subscription->website_url)
@component('mail::button', ['url' => $subscription->website_url])
Manage Subscription
@endcomponent
@endif

## Need Help?

If you have any questions about this bill or need to update your payment information, please contact the service provider directly.

@if($subscription->notes)
**Notes:** {{ $subscription->notes }}
@endif

---

*This is an automated reminder from {{ config('app.name') }}. You can manage your notification preferences in your account settings.*

**Tracking ID:** {{ $trackingId }}

Thanks,<br>
{{ config('app.name') }}
@endcomponent
