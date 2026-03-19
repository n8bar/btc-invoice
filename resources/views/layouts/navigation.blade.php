<nav x-data="{ open: false }" class="sticky top-0 z-30 bg-white border-b border-gray-100 dark:bg-slate-900 dark:border-white/10">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ auth()->check() ? route('dashboard') : url('/') }}">
                        <img src="{{ asset('images/CZ.png') }}" alt="CryptoZing" class="block h-20 w-auto">
                    </a>
                </div>

                <!-- Navigation Links -->
		                <div class="hidden space-x-4 sm:-my-px sm:ms-10 sm:flex">
                        @auth
	                        <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
	                            {{ __('Dashboard') }}
	                        </x-nav-link>
	                        <x-nav-link :href="route('clients.index')" :active="request()->routeIs('clients.*')">
	                            Clients
	                        </x-nav-link>
	                        <x-nav-link :href="route('invoices.index')" :active="request()->routeIs('invoices.*')">
	                            Invoices
	                        </x-nav-link>
                        @endauth
	                    <x-nav-link :href="route('help')" :active="request()->routeIs('help')">
	                        Helpful Notes
	                    </x-nav-link>
	                </div>
	            </div>

            <!-- Settings Dropdown -->
            <div class="hidden sm:flex sm:items-center sm:ms-6 gap-4">
                @php
                    $themePreference = auth()->user()?->theme ?? 'system';
                    $themeEndpoint = auth()->check() ? route('theme.update') : '';
                    $authUser = Auth::user();
                    $walletUnsupported = (bool) ($authUser?->walletSetting?->unsupported_configuration_active ?? false);
                    $requiresReplayConfirm = $authUser?->gettingStartedIsDone() && ! $authUser->gettingStartedWasDismissed();
                    $gettingStartedCompletedOn = $authUser?->getting_started_completed_at?->setTimezone(config('app.timezone'))->format('F j, Y');
                    $gettingStartedReplayConfirmMessage = $gettingStartedCompletedOn
                        ? "You already completed this on {$gettingStartedCompletedOn}. Would you like to run through it again?"
                        : 'You already completed Getting started. Would you like to run through it again?';
                @endphp
                <div class="flex items-center gap-1" role="group" aria-label="Theme" data-theme-endpoint="{{ $themeEndpoint }}" data-theme-initial="{{ $themePreference }}">
                    <button type="button"
                            data-theme-set="light"
                            aria-label="Light theme"
                            aria-pressed="{{ $themePreference === 'light' ? 'true' : 'false' }}"
                            title="Light theme"
                            class="inline-flex h-11 w-11 items-center justify-center rounded-md border border-gray-200 bg-white text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 dark:border-white/25 dark:bg-slate-900/60 dark:text-slate-100 dark:hover:bg-white/10 dark:focus-visible:ring-offset-slate-900">
                        <span aria-hidden="true">☀️</span>
                    </button>
                    <button type="button"
                            data-theme-set="dark"
                            aria-label="Dark theme"
                            aria-pressed="{{ $themePreference === 'dark' ? 'true' : 'false' }}"
                            title="Dark theme"
                            class="inline-flex h-11 w-11 items-center justify-center rounded-md border border-gray-200 bg-white text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 dark:border-white/25 dark:bg-slate-900/60 dark:text-slate-100 dark:hover:bg-white/10 dark:focus-visible:ring-offset-slate-900">
                        <span aria-hidden="true">🌙</span>
                    </button>
                    <button type="button"
                            data-theme-set="system"
                            aria-label="System theme"
                            aria-pressed="{{ $themePreference === 'system' ? 'true' : 'false' }}"
                            title="System theme"
                            class="inline-flex h-11 w-11 items-center justify-center rounded-md border border-gray-200 bg-white text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 dark:border-white/25 dark:bg-slate-900/60 dark:text-slate-100 dark:hover:bg-white/10 dark:focus-visible:ring-offset-slate-900">
                        <span aria-hidden="true">🖥️</span>
                    </button>
                </div>
                @auth
                    <x-dropdown align="right" width="48">
                        <x-slot name="trigger">
                            <button class="inline-flex items-center gap-2 px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none transition ease-in-out duration-150 dark:bg-slate-900/60 dark:text-slate-100 dark:hover:bg-white/10">
                                @if ($walletUnsupported)
                                    <span data-user-menu-unsupported-label
                                          class="inline-flex min-w-[10.25rem] flex-col items-center justify-center rounded-full border border-red-500 bg-red-100 px-4 py-1.5 text-center text-xs font-semibold uppercase leading-[1.05rem] tracking-[0.14em] text-red-900 dark:border-red-100 dark:bg-red-700 dark:text-white">
                                        <span>Unsupported</span>
                                        <span>Configuration</span>
                                    </span>
                                @endif
                                <div>{{ Auth::user()->name }}</div>

                                <div class="ms-1">
                                    <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                            </button>
                        </x-slot>

                        <x-slot name="content">
                            <x-dropdown-link :href="route('settings.index')">
                                <span class="inline-flex items-center gap-2">
                                    <span>{{ __('Settings') }}</span>
                                    @if ($walletUnsupported)
                                        <span data-settings-alert-dot class="inline-block h-2.5 w-2.5 rounded-full bg-red-500"></span>
                                    @endif
                                </span>
                            </x-dropdown-link>
                            @if (Auth::user()->gettingStartedIsDone())
                                <form method="POST"
                                      action="{{ route('getting-started.reopen') }}"
                                      data-getting-started-reopen-mode="{{ $requiresReplayConfirm ? 'confirm' : 'direct' }}"
                                      @if ($requiresReplayConfirm)
                                          data-getting-started-completed-date="{{ $gettingStartedCompletedOn }}"
                                          data-getting-started-reopen-message="{{ $gettingStartedReplayConfirmMessage }}"
                                          onsubmit="return confirm(@js($gettingStartedReplayConfirmMessage));"
                                      @endif>
                                    @csrf
                                    <button type="submit"
                                            class="block w-full px-4 py-2 text-start text-sm leading-5 text-gray-700 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 transition duration-150 ease-in-out">
                                        {{ __('Getting started') }}
                                    </button>
                                </form>
                            @else
                                <x-dropdown-link :href="route('getting-started.start')">
                                    {{ __('Getting started') }}
                                </x-dropdown-link>
                            @endif

                            <!-- Authentication -->
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf

                                <button type="submit"
                                        class="block w-full px-4 py-2 text-start text-sm leading-5 text-gray-700 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 transition duration-150 ease-in-out">
                                    {{ __('Log Out') }}
                                </button>
                            </form>
                        </x-slot>
                    </x-dropdown>
                @endauth

                @guest
                    @if (Route::has('login'))
                        <a href="{{ route('login') }}"
                           class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 focus-visible:ring-offset-gray-100 transition ease-in-out duration-150 dark:bg-slate-900/60 dark:text-slate-100 dark:hover:bg-white/10 dark:focus-visible:ring-offset-slate-900">
                            Log in
                        </a>
                    @endif
                @endguest
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
                @auth
	                <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
	                    {{ __('Dashboard') }}
	                </x-responsive-nav-link>
	                <x-responsive-nav-link :href="route('clients.index')" :active="request()->routeIs('clients.*')">
	                    Clients
	                </x-responsive-nav-link>
	                <x-responsive-nav-link :href="route('invoices.index')" :active="request()->routeIs('invoices.*')">
	                    Invoices
	                </x-responsive-nav-link>
                @endauth
	            <x-responsive-nav-link :href="route('help')" :active="request()->routeIs('help')">
	                Helpful Notes
	            </x-responsive-nav-link>

                @guest
                    @if (Route::has('login'))
                        <x-responsive-nav-link :href="route('login')" :active="request()->routeIs('login')">
                            Log in
                        </x-responsive-nav-link>
                    @endif
                @endguest

	            <div class="px-4">
	                @php
	                    $themePreference = auth()->user()?->theme ?? 'system';
                        $themeEndpoint = auth()->check() ? route('theme.update') : '';
                @endphp
                <div class="flex items-center gap-2" role="group" aria-label="Theme" data-theme-endpoint="{{ $themeEndpoint }}" data-theme-initial="{{ $themePreference }}">
                    <button type="button"
                            data-theme-set="light"
                            aria-label="Light theme"
                            aria-pressed="{{ $themePreference === 'light' ? 'true' : 'false' }}"
                            title="Light theme"
                            class="inline-flex h-11 w-11 items-center justify-center rounded-md border border-gray-200 bg-white text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 dark:border-white/25 dark:bg-slate-900/60 dark:text-slate-100 dark:hover:bg-white/10 dark:focus-visible:ring-offset-slate-900">
                        <span aria-hidden="true">☀️</span>
                    </button>
                    <button type="button"
                            data-theme-set="dark"
                            aria-label="Dark theme"
                            aria-pressed="{{ $themePreference === 'dark' ? 'true' : 'false' }}"
                            title="Dark theme"
                            class="inline-flex h-11 w-11 items-center justify-center rounded-md border border-gray-200 bg-white text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 dark:border-white/25 dark:bg-slate-900/60 dark:text-slate-100 dark:hover:bg-white/10 dark:focus-visible:ring-offset-slate-900">
                        <span aria-hidden="true">🌙</span>
                    </button>
                    <button type="button"
                            data-theme-set="system"
                            aria-label="System theme"
                            aria-pressed="{{ $themePreference === 'system' ? 'true' : 'false' }}"
                            title="System theme"
                            class="inline-flex h-11 w-11 items-center justify-center rounded-md border border-gray-200 bg-white text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 dark:border-white/25 dark:bg-slate-900/60 dark:text-slate-100 dark:hover:bg-white/10 dark:focus-visible:ring-offset-slate-900">
                        <span aria-hidden="true">🖥️</span>
                    </button>
                </div>
            </div>

        </div>

        <!-- Responsive Settings Options -->
        @auth
            <div class="pt-4 pb-1 border-t border-gray-200 dark:border-white/10">
                <div class="px-4">
                    <div class="font-medium text-base text-gray-800 dark:text-slate-100">{{ Auth::user()->name }}</div>
                    <div class="font-medium text-sm text-gray-500 dark:text-slate-300">{{ Auth::user()->email }}</div>
                    @if ($walletUnsupported)
                        <div class="mt-2">
                            <span data-user-menu-unsupported-label
                                  class="inline-flex min-w-[10.25rem] flex-col items-center justify-center rounded-full border border-red-500 bg-red-100 px-4 py-1.5 text-center text-xs font-semibold uppercase leading-[1.05rem] tracking-[0.14em] text-red-900 dark:border-red-100 dark:bg-red-700 dark:text-white">
                                <span>Unsupported</span>
                                <span>Configuration</span>
                            </span>
                        </div>
                    @endif
                </div>

                <div class="mt-3 space-y-1">
                    <x-responsive-nav-link :href="route('settings.index')" :active="request()->routeIs('profile.edit', 'settings.invoice.*', 'wallet.settings.*', 'settings.notifications.*')">
                        <span class="inline-flex items-center gap-2">
                            <span>{{ __('Settings') }}</span>
                            @if ($walletUnsupported)
                                <span data-settings-alert-dot class="inline-block h-2.5 w-2.5 rounded-full bg-red-500"></span>
                            @endif
                        </span>
                    </x-responsive-nav-link>
                    @if (Auth::user()->gettingStartedIsDone())
                        <form method="POST"
                              action="{{ route('getting-started.reopen') }}"
                              data-getting-started-reopen-mode="{{ $requiresReplayConfirm ? 'confirm' : 'direct' }}"
                              @if ($requiresReplayConfirm)
                                  data-getting-started-completed-date="{{ $gettingStartedCompletedOn }}"
                                  data-getting-started-reopen-message="{{ $gettingStartedReplayConfirmMessage }}"
                                  onsubmit="return confirm(@js($gettingStartedReplayConfirmMessage));"
                              @endif>
                            @csrf
                            <button type="submit"
                                    class="block w-full ps-3 pe-4 py-2 border-l-4 border-transparent text-start text-base font-medium text-gray-600 hover:text-gray-800 hover:bg-gray-50 hover:border-gray-300 focus:outline-none focus:text-gray-800 focus:bg-gray-50 focus:border-gray-300 transition duration-150 ease-in-out">
                                {{ __('Getting started') }}
                            </button>
                        </form>
                    @else
                        <x-responsive-nav-link :href="route('getting-started.start')" :active="request()->routeIs('getting-started.*')">
                            {{ __('Getting started') }}
                        </x-responsive-nav-link>
                    @endif

                    <!-- Authentication -->
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf

                        <button type="submit"
                                class="block w-full ps-3 pe-4 py-2 border-l-4 border-transparent text-start text-base font-medium text-gray-600 hover:text-gray-800 hover:bg-gray-50 hover:border-gray-300 focus:outline-none focus:text-gray-800 focus:bg-gray-50 focus:border-gray-300 transition duration-150 ease-in-out">
                            {{ __('Log Out') }}
                        </button>
                    </form>
                </div>
            </div>
        @endauth
    </div>
</nav>
<script>
    (() => {
        const applyTheme = (theme) => {
            const root = document.documentElement;
            const body = document.body;
            const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
            const useDark = theme === 'dark' || (theme === 'system' && prefersDark);

            [root, body].forEach((node) => {
                if (!node) return;
                node.classList.toggle('dark', useDark);
            });

            root.dataset.theme = theme;
        };

        const persistTheme = (endpoint, theme) => {
            fetch(endpoint, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({ theme }),
            }).catch(() => {});
        };

        (() => {
            document.querySelectorAll('[data-theme-endpoint]').forEach((container) => {
                const endpoint = container.getAttribute('data-theme-endpoint');
                const saved = localStorage.getItem('theme');
                const initial = saved || container.getAttribute('data-theme-initial') || document.documentElement.dataset.theme || 'system';
                const buttons = Array.from(container.querySelectorAll('[data-theme-set]'));

                const setActive = (theme) => {
                    buttons.forEach((btn) => {
                        const isActive = btn.getAttribute('data-theme-set') === theme;
                        btn.classList.toggle('border-indigo-500', isActive);
                        btn.classList.toggle('text-indigo-700', isActive);
                        btn.classList.toggle('bg-indigo-50', isActive);
                        btn.classList.toggle('dark:text-indigo-200', isActive);
                        btn.classList.toggle('dark:bg-indigo-500/20', isActive);
                        btn.classList.toggle('border-gray-200', !isActive);
                        btn.classList.toggle('text-gray-700', !isActive);
                        btn.classList.toggle('bg-white', !isActive);
                        btn.setAttribute('aria-pressed', isActive ? 'true' : 'false');
                    });
                };

                setActive(initial);
                applyTheme(initial);
                localStorage.setItem('theme', initial);

                buttons.forEach((btn) => {
                    btn.addEventListener('click', () => {
                        const theme = btn.getAttribute('data-theme-set');
                        applyTheme(theme);
                        setActive(theme);
                        localStorage.setItem('theme', theme);
                        if (endpoint) {
                            persistTheme(endpoint, theme);
                        }
                    });
                });
            });
        })();
    })();
</script>
