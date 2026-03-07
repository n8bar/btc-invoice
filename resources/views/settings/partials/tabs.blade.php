@php
    $tabs = [
        [
            'label' => 'Profile',
            'href' => route('profile.edit'),
            'active' => request()->routeIs('profile.edit'),
        ],
        [
            'label' => 'Wallet',
            'href' => route('wallet.settings.edit'),
            'active' => request()->routeIs('wallet.settings.*'),
        ],
        [
            'label' => 'Invoices',
            'href' => route('settings.invoice.edit'),
            'active' => request()->routeIs('settings.invoice.*'),
        ],
        [
            'label' => 'Notifications',
            'href' => route('settings.notifications.edit'),
            'active' => request()->routeIs('settings.notifications.*'),
        ],
    ];
@endphp

<nav class="rounded-lg border border-gray-200 bg-white p-2 shadow-sm" aria-label="Settings">
    <ul class="flex flex-wrap gap-2">
        @foreach ($tabs as $tab)
            <li>
                <a href="{{ $tab['href'] }}"
                   @class([
                       'inline-flex items-center rounded-md px-3 py-2 text-sm font-medium transition',
                       'bg-indigo-600 text-white shadow-sm' => $tab['active'],
                       'bg-white text-gray-700 ring-1 ring-gray-200 hover:bg-gray-50' => ! $tab['active'],
                   ])>
                    {{ $tab['label'] }}
                </a>
            </li>
        @endforeach
    </ul>
</nav>
