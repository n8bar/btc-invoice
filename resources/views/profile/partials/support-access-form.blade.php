@php
    $supportGrantHours = (int) config('support.grant_hours', 72);
    $supportAccessActive = $user->hasActiveSupportAccessGrant();
    $supportAccessExpiresAt = $user->support_access_expires_at?->setTimezone(config('app.timezone'));
@endphp

<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900 dark:text-slate-100">
            Tech Support Access
        </h2>

        <p class="mt-1 text-sm text-gray-600 dark:text-slate-300">
            Grant CryptoZing tech support temporary read-only access to your invoices and clients for troubleshooting.
        </p>
    </header>

    @if (session('status') === 'support-access-granted')
        <div class="mt-4 rounded-lg border border-green-300 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-400/40 dark:bg-green-950/30 dark:text-green-100">
            Tech support access is active until {{ $supportAccessExpiresAt?->toDayDateTimeString() ?? 'the configured expiration time' }}.
        </div>
    @elseif (session('status') === 'support-access-revoked')
        <div class="mt-4 rounded-lg border border-green-300 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-400/40 dark:bg-green-950/30 dark:text-green-100">
            Tech support access has been revoked.
        </div>
    @endif

    <div class="mt-6 rounded-lg border border-gray-200 bg-white p-4 text-sm text-gray-700 shadow-sm dark:border-white/10 dark:bg-slate-900/70 dark:text-slate-200">
        <p class="font-semibold text-gray-900 dark:text-white">Permission summary</p>
        <p class="mt-2">
            By enabling this setting, you authorize CryptoZing tech support to view your invoices and clients for troubleshooting.
            This access is read-only, expires automatically after {{ $supportGrantHours }} hours, and you can revoke it at any time.
        </p>

        @if ($supportAccessActive)
            <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-400/40 dark:bg-amber-950/30 dark:text-amber-100">
                <p class="font-semibold">Support access is active</p>
                <p class="mt-1">Expires {{ $supportAccessExpiresAt?->toDayDateTimeString() ?? 'soon' }}.</p>
            </div>

            <form method="POST" action="{{ route('settings.support-access.revoke') }}" class="mt-4">
                @csrf
                @method('DELETE')
                <x-danger-button>Revoke Support Access</x-danger-button>
            </form>
        @else
            <div class="mt-4 rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm text-indigo-900 dark:border-indigo-400/40 dark:bg-indigo-950/30 dark:text-indigo-100">
                <p class="font-semibold">Support access is off</p>
                <p class="mt-1">If you grant access now, it will expire automatically after {{ $supportGrantHours }} hours.</p>
            </div>

            <form method="POST" action="{{ route('settings.support-access.grant') }}" class="mt-4">
                @csrf
                @method('PATCH')
                <x-primary-button>Grant Temporary Support Access</x-primary-button>
            </form>
        @endif
    </div>
</section>
