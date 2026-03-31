@component('mail::message', ['user' => $user])
# Branded test message

Hi {{ $user->name ?? 'there' }},

This is a branded test message using your current saved email branding settings.

Payment acknowledgments, receipts, paid notices, and alerts keep their own standard subject and body copy.

@component('mail::button', ['url' => route('settings.notifications.edit')])
Review notification settings
@endcomponent

Thanks,<br>
{{ $user->name ?? $user->effectiveMailBrandName() }}
@endcomponent
