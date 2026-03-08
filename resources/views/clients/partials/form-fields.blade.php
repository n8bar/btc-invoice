@php
    $client = $client ?? null;
    $showNotes = $showNotes ?? true;
@endphp

<div>
    <label class="block text-sm font-medium text-gray-700">
        Name <span class="text-red-600" aria-hidden="true">*</span>
    </label>
    <input name="name"
           value="{{ old('name', $client->name ?? '') }}"
           required
           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"/>
    @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
</div>

<div>
    <label class="block text-sm font-medium text-gray-700">
        Email <span class="text-red-600" aria-hidden="true">*</span>
    </label>
    <input type="email"
           name="email"
           value="{{ old('email', $client->email ?? '') }}"
           required
           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"/>
    @error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
</div>

@if ($showNotes)
    <div>
        <label class="block text-sm font-medium text-gray-700">Notes</label>
        <textarea name="notes"
                  rows="4"
                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('notes', $client->notes ?? '') }}</textarea>
        @error('notes')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
@endif
