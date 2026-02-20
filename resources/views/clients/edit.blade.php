<x-emoji-favicon symbol="🧩" bg="#E0F2F1" />
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-xl font-semibold leading-tight">Edit Client</h2>
                <p class="text-sm text-gray-500">Update contact details used for invoice delivery.</p>
            </div>
            <a href="{{ route('clients.index') }}" class="text-sm text-gray-600 hover:underline">Back to clients</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-2xl sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 rounded-md bg-green-50 p-3 text-green-700">
                    {{ session('status') }}
                </div>
            @endif

            <div class="rounded-lg bg-white p-6 shadow">
                <form method="POST" action="{{ route('clients.update', $client) }}" class="space-y-6">
                    @csrf
                    @method('PUT')

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Name</label>
                        <input name="name" value="{{ old('name', $client->name) }}" required
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"/>
                        @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Email</label>
                        <input type="email" name="email" value="{{ old('email', $client->email) }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"/>
                        @error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Notes</label>
                        <textarea name="notes" rows="4"
                                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('notes', $client->notes) }}</textarea>
                        @error('notes')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div class="flex items-center justify-end gap-3">
                        <a href="{{ route('clients.index') }}" class="text-gray-600 hover:underline">Cancel</a>
                        <x-primary-button>Save changes</x-primary-button>
                    </div>
                </form>
            </div>

            <div class="mt-8 rounded-md border border-red-200 bg-red-50 p-4 text-red-700" style="border-color: currentColor;">
                <h3 class="text-sm font-semibold">Delete client</h3>
                <p class="mt-1 text-xs text-red-600">This moves the client to trash. You can restore them later.</p>
                <form method="POST"
                      action="{{ route('clients.destroy', $client) }}"
                      onsubmit="return confirm('Delete client {{ $client->name }}? This moves them to trash.');"
                      class="mt-3">
                    @csrf
                    @method('DELETE')
                    <x-danger-button type="submit">Delete client</x-danger-button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
