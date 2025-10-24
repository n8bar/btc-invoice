<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight">Edit Client</h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-2xl sm:px-6 lg:px-8">
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
    <x-primary-button>Save</x-primary-button>
</div>
</form>
</div>
</div>
</x-app-layout>
