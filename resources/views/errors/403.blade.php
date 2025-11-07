@php
    $details = isset($details) && is_string($details) && trim($details) !== '' ? $details : null;
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Access denied') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 space-y-4">
                    <p>@lang("Sorry, you don't have permission.")</p>
                    @if ($details)
                        <p class="text-gray-600">{{ $details }}</p>
                    @endif
                    <p>
                        <a href="{{ route('dashboard') }}" class="text-blue-600 underline">
                            &larr; {{ __('Back to dashboard') }}
                        </a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
