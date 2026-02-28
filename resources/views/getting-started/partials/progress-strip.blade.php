@php
    $currentStep = $strip['current_step'];
    $currentStepKey = $strip['current_step_key'];
@endphp

<div class="rounded-lg border border-indigo-200 bg-indigo-50/80 p-4 shadow-sm dark:border-indigo-400/30 dark:bg-indigo-950/35" style="border-color: currentColor;">
    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.15em] text-indigo-700 dark:text-indigo-200">Getting Started</p>
            <p class="mt-1 text-sm font-semibold text-indigo-950 dark:text-indigo-100">
                Step {{ $currentStep['position'] }} of {{ $strip['step_count'] }}: {{ $currentStep['label'] }}
            </p>
            <p class="mt-1 text-xs text-indigo-900 dark:text-indigo-200">
                {{ $currentStep['criteria'] }}
            </p>
        </div>
        <a href="{{ $strip['back_url'] }}"
           class="inline-flex items-center rounded-md border border-indigo-300 bg-white px-3 py-2 text-sm font-semibold text-indigo-700 hover:bg-indigo-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:border-indigo-400/35 dark:bg-slate-900/75 dark:text-indigo-100 dark:hover:bg-indigo-950/50 dark:focus:ring-offset-slate-900">
            {{ $strip['back_label'] ?? 'Back' }}
        </a>
    </div>

    <ol class="mt-3 flex flex-wrap items-center gap-2" aria-label="Getting started progress">
        @foreach ($strip['steps'] as $step)
            @php $isCurrent = $step['key'] === $currentStepKey; @endphp
            <li class="inline-flex items-center gap-2 rounded-full border px-3 py-1 text-xs font-semibold {{ $step['complete'] ? 'border-green-200 bg-green-50 text-green-700 dark:border-emerald-400/30 dark:bg-emerald-950/40 dark:text-emerald-200' : ($isCurrent ? 'border-indigo-300 bg-white text-indigo-700 dark:border-indigo-400/35 dark:bg-slate-900/75 dark:text-indigo-100' : 'border-indigo-200 bg-indigo-50 text-indigo-800 dark:border-indigo-400/25 dark:bg-indigo-950/35 dark:text-indigo-200') }}"
                @if ($isCurrent) aria-current="step" @endif>
                <span aria-hidden="true">{{ $step['complete'] ? '✓' : $step['position'] }}</span>
                <span>{{ $step['label'] }}</span>
            </li>
        @endforeach
    </ol>
</div>
