<nav x-data="{ open: false }" class="bg-white border-b border-gray-100">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}">
                        <img src="{{ asset('images/CZ.png') }}" alt="CryptoZing" class="block h-9 w-auto">
                    </a>
                </div>

                <!-- Navigation Links -->
                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                        {{ __('Dashboard') }}
                    </x-nav-link>
                    <x-nav-link :href="route('clients.index')" :active="request()->routeIs('clients.*')">
                        Clients
                    </x-nav-link>
                    <x-nav-link :href="route('invoices.index')" :active="request()->routeIs('invoices.*')">
                        Invoices
                    </x-nav-link>
                </div>
            </div>

            <!-- Settings Dropdown -->
            <div class="hidden sm:flex sm:items-center sm:ms-6 gap-4">
                @php
                    $themePreference = auth()->user()?->theme ?? 'system';
                @endphp
                <div x-data="themeToggle({ endpoint: '{{ route('theme.update') }}', initial: '{{ $themePreference }}' })" class="flex items-center gap-1">
                    <button type="button" @click="set('light')" :class="buttonClass('light')" class="px-2 py-1 rounded-md text-sm font-semibold border border-gray-200 bg-white text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none">
                        ☀️
                    </button>
                    <button type="button" @click="set('dark')" :class="buttonClass('dark')" class="px-2 py-1 rounded-md text-sm font-semibold border border-gray-200 bg-white text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none">
                        🌙
                    </button>
                    <button type="button" @click="set('system')" :class="buttonClass('system')" class="px-2 py-1 rounded-md text-sm font-semibold border border-gray-200 bg-white text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none">
                        🖥️
                    </button>
                </div>
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none transition ease-in-out duration-150">
                            <div>{{ Auth::user()->name }}</div>

                            <div class="ms-1">
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile.edit')">
                            {{ __('Profile') }}
                        </x-dropdown-link>
                        <x-dropdown-link :href="route('settings.invoice.edit')">
                            {{ __('Invoice Settings') }}
                        </x-dropdown-link>
                        <x-dropdown-link :href="route('wallet.settings.edit')">
                            {{ __('Wallet Settings') }}
                        </x-dropdown-link>

                        <!-- Authentication -->
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf

                            <x-dropdown-link :href="route('logout')"
                                    onclick="event.preventDefault();
                                                this.closest('form').submit();">
                                {{ __('Log Out') }}
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

            <!-- Hamburger -->
            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500 transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden">
        <div class="pt-2 pb-3 space-y-1">
            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                {{ __('Dashboard') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('clients.index')" :active="request()->routeIs('clients.*')">
                Clients
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('invoices.index')" :active="request()->routeIs('invoices.*')">
                Invoices
            </x-responsive-nav-link>

            <div class="px-4">
                @php
                    $themePreference = auth()->user()?->theme ?? 'system';
                @endphp
                <div x-data="themeToggle({ endpoint: '{{ route('theme.update') }}', initial: '{{ $themePreference }}' })" class="flex items-center gap-2">
                    <button type="button" @click="set('light')" :class="buttonClass('light')" class="px-2 py-1 rounded-md text-sm font-semibold border border-gray-200 bg-white text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none">
                        ☀️
                    </button>
                    <button type="button" @click="set('dark')" :class="buttonClass('dark')" class="px-2 py-1 rounded-md text-sm font-semibold border border-gray-200 bg-white text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none">
                        🌙
                    </button>
                    <button type="button" @click="set('system')" :class="buttonClass('system')" class="px-2 py-1 rounded-md text-sm font-semibold border border-gray-200 bg-white text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none">
                        🖥️
                    </button>
                </div>
            </div>

        </div>

        <!-- Responsive Settings Options -->
        <div class="pt-4 pb-1 border-t border-gray-200">
            <div class="px-4">
                <div class="font-medium text-base text-gray-800">{{ Auth::user()->name }}</div>
                <div class="font-medium text-sm text-gray-500">{{ Auth::user()->email }}</div>
            </div>

            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('profile.edit')">
                    {{ __('Profile') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('settings.invoice.edit')" :active="request()->routeIs('settings.invoice.*')">
                    {{ __('Invoice Settings') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('wallet.settings.edit')" :active="request()->routeIs('wallet.settings.*')">
                    {{ __('Wallet Settings') }}
                </x-responsive-nav-link>

                <!-- Authentication -->
                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <x-responsive-nav-link :href="route('logout')"
                            onclick="event.preventDefault();
                                        this.closest('form').submit();">
                        {{ __('Log Out') }}
                    </x-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>
</nav>
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('themeToggle', ({ endpoint, initial }) => ({
            theme: initial || 'system',
            init() {
                this.apply(this.theme);
            },
            buttonClass(target) {
                return this.theme === target
                    ? 'border-indigo-500 text-indigo-700'
                    : 'border-gray-200 text-gray-700';
            },
            set(theme) {
                this.theme = theme;
                this.apply(theme);
                this.persist(theme);
            },
            apply(theme) {
                const root = document.documentElement;
                const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
                const useDark = theme === 'dark' || (theme === 'system' && prefersDark);
                root.classList.toggle('dark', useDark);
                root.dataset.theme = theme;
            },
            persist(theme) {
                fetch(endpoint, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ theme }),
                }).catch(() => {});
            },
        }));
    });
</script>
