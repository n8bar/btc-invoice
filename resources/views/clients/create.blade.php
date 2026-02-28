<x-emoji-favicon symbol="➕" bg="#F3E8FF" />
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-xl font-semibold leading-tight">New Client</h2>
                <p class="text-sm text-gray-500">Add a billing contact for future invoices.</p>
            </div>
            <a href="{{ route('clients.index') }}" class="text-sm text-gray-600 hover:underline">Back to clients</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-2xl sm:px-6 lg:px-8">
            <div class="rounded-lg bg-white p-6 shadow">
                <form method="POST" action="{{ route('clients.store') }}" class="space-y-6">
                @csrf

                @include('clients.partials.form-fields')

                <div class="flex items-center justify-end gap-3">
                    <a href="{{ route('clients.index') }}" class="text-gray-600 hover:underline">Cancel</a>
                    <x-primary-button>Create client</x-primary-button>
                </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
