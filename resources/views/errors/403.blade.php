<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">Access denied</h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            <div class="rounded-lg bg-white p-8 shadow">
                <h3 class="text-lg font-semibold text-gray-900">You don't have permission to view this page.</h3>
                <p class="mt-4 text-sm text-gray-600">
                    This resource belongs to another account or no longer exists. If you believe this is a mistake, please
                    double-check the URL or contact the owner of the record.
                </p>
                <div class="mt-6 flex items-center gap-3">
                    <a href="{{ url()->previous() }}" class="text-sm text-indigo-600 hover:text-indigo-500 hover:underline">Go back</a>
                    <span class="text-gray-300">|</span>
                    <a href="{{ route('dashboard') }}" class="text-sm text-gray-600 hover:text-gray-900">Return to dashboard</a>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
