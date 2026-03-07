@php
    $tabs = [
        [
            'label' => 'Account',
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

<nav class="border-b border-gray-200" aria-label="Settings">
    <ul class="-mb-px flex flex-wrap gap-x-4 gap-y-2">
        @foreach ($tabs as $tab)
            <li>
                <a href="{{ $tab['href'] }}"
                   @class([
                       'inline-flex items-center border-b-2 px-1 pb-2 text-xs font-medium leading-5 transition duration-150 ease-in-out',
                       'border-indigo-400 text-gray-900 focus:border-indigo-700 focus:text-gray-900' => $tab['active'],
                       'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 focus:border-gray-300 focus:text-gray-700' => ! $tab['active'],
                   ])>
                    {{ $tab['label'] }}
                </a>
            </li>
        @endforeach
    </ul>
</nav>
