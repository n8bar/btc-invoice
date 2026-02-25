<x-emoji-favicon symbol="🧭" bg="#DBEAFE" />
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-indigo-600 dark:text-indigo-300">
                    Getting Started
                </p>
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-slate-100">
                    {{ $currentStep['title'] }}
                </h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-slate-300">
                    Step {{ $currentStepNumber }} of {{ $stepCount }}: {{ $currentStep['label'] }}
                </p>
            </div>
            <a href="{{ $backUrl }}" class="text-sm text-gray-600 hover:underline dark:text-slate-300 dark:hover:text-slate-100">
                Back to dashboard
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-4xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-md border border-green-200 bg-green-50 p-4 text-sm text-green-800" role="status" aria-live="polite">
                    {{ session('status') }}
                </div>
            @endif

            <div class="grid gap-6 lg:grid-cols-[1.15fr_0.85fr]">
                <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-white/15 dark:bg-slate-900/70">
                    <div class="space-y-4">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900 dark:text-slate-100">{{ $currentStep['title'] }}</h3>
                            <p class="mt-2 text-sm leading-6 text-gray-600 dark:text-slate-300">
                                {{ $currentStep['body'] }}
                            </p>
                        </div>

                        <div class="rounded-lg border border-indigo-100 bg-indigo-50 p-4 text-sm text-indigo-900 dark:border-indigo-400/25 dark:bg-indigo-950/35 dark:text-indigo-100" style="border-color: currentColor;">
                            <p class="font-semibold">Success criteria</p>
                            <p class="mt-1">{{ $currentStep['criteria'] }}</p>
                        </div>

                        @if ($currentStepKey === 'deliver' && $deliverInvoice)
                            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm text-gray-700 dark:border-white/15 dark:bg-slate-900/60 dark:text-slate-200">
                                <p class="font-semibold text-gray-900 dark:text-slate-100">Target invoice</p>
                                <p class="mt-1">
                                    <span class="font-medium">#{{ $deliverInvoice->number }}</span>
                                    @if ($deliverInvoice->client)
                                        <span class="text-gray-500 dark:text-slate-400">for {{ $deliverInvoice->client->name }}</span>
                                    @endif
                                </p>
                                <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">
                                    Enable the public link, then use the send form on the invoice page.
                                </p>
                            </div>
                        @endif

                        @if ($currentStepKey !== $earliestIncompleteStep)
                            <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900" style="border-color: currentColor;">
                                You can review this step, but the next required step is still step
                                {{ collect($steps)->firstWhere('key', $earliestIncompleteStep)['position'] ?? '—' }}.
                            </div>
                        @endif

                        <div class="flex flex-wrap items-center gap-3">
                            <a href="{{ $actionUrl }}"
                               class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                                {{ $currentStep['cta_label'] }}
                            </a>

                            <form method="POST" action="{{ route('getting-started.dismiss') }}">
                                @csrf
                                <button type="submit"
                                        class="inline-flex items-center rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:border-white/20 dark:text-slate-200 dark:hover:bg-white/5 dark:focus:ring-offset-slate-900">
                                    Hide getting started
                                </button>
                            </form>
                        </div>
                    </div>
                </section>

                <aside class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-white/15 dark:bg-slate-900/70">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-slate-100">Progress</h3>
                    <ol class="mt-4 space-y-3" aria-label="Getting started progress">
                        @foreach ($steps as $step)
                            @php
                                $isCurrent = $step['key'] === $currentStepKey;
                            @endphp
                            <li class="rounded-lg border p-3 {{ $isCurrent ? 'border-indigo-300 bg-indigo-50 dark:border-indigo-400/30 dark:bg-indigo-950/35' : 'border-gray-200 bg-white dark:border-white/10 dark:bg-slate-900/50' }}"
                                @if ($isCurrent) aria-current="step" @endif>
                                <div class="flex items-start gap-3">
                                    <div class="mt-0.5 inline-flex h-6 w-6 items-center justify-center rounded-full text-xs font-semibold {{ $step['complete'] ? 'bg-green-100 text-green-700 dark:bg-emerald-950/45 dark:text-emerald-200' : ($isCurrent ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/60 dark:text-indigo-200' : 'bg-gray-100 text-gray-600 dark:bg-slate-800 dark:text-slate-300') }}">
                                        @if ($step['complete'])
                                            ✓
                                        @else
                                            {{ $step['position'] }}
                                        @endif
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-gray-900 dark:text-slate-100">{{ $step['label'] }}</p>
                                        <p class="mt-1 text-xs text-gray-600 dark:text-slate-300">{{ $step['criteria'] }}</p>
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                </aside>
            </div>
        </div>
    </div>
</x-app-layout>
