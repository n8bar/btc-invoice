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
                    <p>{{ __("Sorry, you don't have permission to view this page.") }}</p>
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
