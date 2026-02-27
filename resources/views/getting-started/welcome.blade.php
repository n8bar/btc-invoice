<x-emoji-favicon symbol="🧭" bg="#DBEAFE" />
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-indigo-600 dark:text-indigo-300">
                    Getting Started
                </p>
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-slate-100">
                    Welcome to CryptoZing
                </h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-slate-300">
                    Let’s get you up and running.
                </p>
            </div>
            <a href="{{ $backUrl }}" class="text-sm text-gray-600 hover:underline dark:text-slate-300 dark:hover:text-slate-100">
                Back to dashboard
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-3xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-md border border-green-200 bg-green-50 p-4 text-sm text-green-800" role="status" aria-live="polite">
                    {{ session('status') }}
                </div>
            @endif

            <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-white/15 dark:bg-slate-900/70">
                <h3 class="text-base font-semibold text-gray-900 dark:text-slate-100">
                    You could be sending your first invoice in minutes.
                </h3>
                <p class="mt-2 text-sm leading-6 text-gray-600 dark:text-slate-300">
                    Setup takes {{ $stepCount }} straightforward steps.
                </p>

                <ol class="mt-4 list-decimal space-y-2 pl-5 text-sm text-gray-700 dark:text-slate-200">
                    <li>Connect your wallet account key.</li>
                    <li>Create your first invoice.</li>
                    <li>Enable the public link and send the invoice.</li>
                </ol>

                <div class="mt-6 flex flex-wrap items-center gap-3">
                    <a href="{{ $startUrl }}"
                       class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                        Start setup
                    </a>

                    <form method="POST" action="{{ route('getting-started.dismiss') }}">
                        @csrf
                        <button type="submit"
                                class="inline-flex items-center rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:border-white/20 dark:text-slate-200 dark:hover:bg-white/5 dark:focus:ring-offset-slate-900">
                            Hide getting started
                        </button>
                    </form>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
