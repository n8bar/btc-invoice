@component('mail::message', ['user' => $user])
# Test email

Hi {{ $user->name ?? 'there' }},

This confirms that outgoing email delivery is working for your account. Payment acknowledgments, receipts, paid notices, and alerts will be sent from this address.

@component('mail::button', ['url' => route('settings.notifications.edit')])
Review notification settings
@endcomponent

Thanks,<br>
{{ $user->name ?? $user->effectiveMailBrandName() }}
@endcomponent
