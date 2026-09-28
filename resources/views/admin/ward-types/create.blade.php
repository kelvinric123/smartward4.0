<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="font-bold text-2xl text-gray-800 leading-tight">
                {{ __('Add Ward Type') }}
            </h2>
            <p class="text-sm text-gray-500 mt-1">Create a new ward type</p>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white/90 backdrop-blur-sm overflow-hidden shadow-lg rounded-2xl border border-blue-100">
                <div class="p-8 text-gray-900">
                    <form method="POST" action="{{ route('ward-types.store') }}">
                        @csrf

                        <div class="mb-4">
                            <label for="hospital_id" class="block text-sm font-medium text-gray-700">Hospital</label>
                            <select name="hospital_id" id="hospital_id"
                                class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">All hospitals (system ward type)</option>
                                @foreach ($hospitals as $hospital)
                                    <option value="{{ $hospital->id }}" {{ old('hospital_id') == $hospital->id ? 'selected' : '' }}>
                                        {{ $hospital->name }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-gray-500">Pick a hospital to add a ward type just for them. Leave as
                                "All hospitals" to make it available everywhere.</p>
                            @error('hospital_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="code" class="block text-sm font-medium text-gray-700">Code <span
                                    class="text-red-500">*</span></label>
                            <input type="text" name="code" id="code" value="{{ old('code') }}" required maxlength="20"
                                class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <p class="mt-1 text-xs text-gray-500">Short identifier, e.g. ICU, HDU, PAED. Saved in uppercase.
                            </p>
                            @error('code')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="name" class="block text-sm font-medium text-gray-700">Ward Type <span
                                    class="text-red-500">*</span></label>
                            <input type="text" name="name" id="name" value="{{ old('name') }}" required
                                class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            @error('name')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700">Critical Care Ward</label>
                            <div class="mt-2 flex items-center gap-4">
                                <label class="inline-flex items-center">
                                    <input type="radio" name="is_critical_care" value="0"
                                        {{ !old('is_critical_care', false) ? 'checked' : '' }}
                                        class="form-radio text-blue-600 focus:ring-blue-500">
                                    <span class="ml-2 text-gray-700">No</span>
                                </label>
                                <label class="inline-flex items-center">
                                    <input type="radio" name="is_critical_care" value="1"
                                        {{ old('is_critical_care', false) ? 'checked' : '' }}
                                        class="form-radio text-blue-600 focus:ring-blue-500">
                                    <span class="ml-2 text-gray-700">Yes</span>
                                </label>
                            </div>
                            <p class="mt-1 text-xs text-gray-500">Wards of this type are listed on the Critical Care Ward
                                Dashboard.</p>
                            @error('is_critical_care')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Clinical Indicator</label>
                            <p class="text-xs text-gray-500 mb-3">Select one or more scoring scales for this ward type.
                                Manage the list on the Clinical Indicator tab.</p>
                            @error('clinical_indicator_ids')
                                <p class="mb-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            @error('clinical_indicator_ids.*')
                                <p class="mb-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            @include('admin.ward-types.partials.indicator-picker', [
                                'selectedIds' => old('clinical_indicator_ids', []),
                            ])
                        </div>

                        <div class="mb-4">
                            <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
                            <textarea name="description" id="description" rows="3"
                                class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ old('description') }}</textarea>
                            @error('description')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="sort_order" class="block text-sm font-medium text-gray-700">Display Order</label>
                            <input type="number" name="sort_order" id="sort_order" value="{{ old('sort_order') }}" min="0"
                                class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <p class="mt-1 text-xs text-gray-500">Leave blank to add to the end of the list.</p>
                            @error('sort_order')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="flex items-center justify-end gap-4 mt-6">
                            <a href="{{ route('ward-types.index') }}"
                                class="inline-flex items-center px-4 py-2 bg-gray-100 hover:bg-gray-200 border border-gray-300 rounded-lg font-semibold text-xs text-gray-700 uppercase tracking-widest transition-colors">
                                Cancel
                            </a>
                            <button type="submit"
                                class="inline-flex items-center px-5 py-2.5 bg-gradient-to-r from-brand-600 to-accent-600 hover:from-brand-700 hover:to-accent-700 border border-transparent rounded-lg font-semibold text-sm text-white shadow-lg hover:shadow-xl transition-all duration-200">
                                Save
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
