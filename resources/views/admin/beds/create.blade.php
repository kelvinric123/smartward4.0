<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-gray-800 leading-tight">
            {{ __('Add Bed') }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white/90 backdrop-blur-sm overflow-hidden shadow-lg rounded-2xl border border-blue-100">
                <div class="p-8 text-gray-900">
                    <form method="POST" action="{{ route('beds.store') }}">
                        @csrf

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="mb-4">
                                <label for="ward_id"
                                    class="block text-sm font-medium text-gray-700">Ward <span
                                        class="text-red-500">*</span></label>
                                <select name="ward_id" id="ward_id" required
                                    class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <option value="">Select Ward</option>
                                    @foreach($wards as $ward)
                                        <option value="{{ $ward->id }}" {{ old('ward_id') == $ward->id ? 'selected' : '' }}>
                                            {{ $ward->ward_name }} ({{ $ward->ward_code }})
                                        </option>
                                    @endforeach
                                </select>
                                @error('ward_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="mb-4">
                                <label for="section"
                                    class="block text-sm font-medium text-gray-700">Section</label>
                                <select name="section" id="section"
                                    class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <option value="">Select Section (Optional)</option>
                                    <option value="1" {{ old('section') == '1' ? 'selected' : '' }}>Section 1</option>
                                    <option value="2" {{ old('section') == '2' ? 'selected' : '' }}>Section 2</option>
                                    <option value="3" {{ old('section') == '3' ? 'selected' : '' }}>Section 3</option>
                                    <option value="4" {{ old('section') == '4' ? 'selected' : '' }}>Section 4</option>
                                </select>
                                @error('section')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="mb-4">
                                <label for="bed_number"
                                    class="block text-sm font-medium text-gray-700">Bed Number <span
                                        class="text-red-500">*</span></label>
                                <input type="text" name="bed_number" id="bed_number" value="{{ old('bed_number') }}"
                                    required
                                    class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                @error('bed_number')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="mb-4">
                                <label for="bed_id"
                                    class="block text-sm font-medium text-gray-700">Bed ID <span
                                        class="text-red-500">*</span></label>
                                <input type="text" name="bed_id" id="bed_id" value="{{ old('bed_id') }}" required
                                    class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                @error('bed_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="mb-4">
                                <label for="bed_display_name"
                                    class="block text-sm font-medium text-gray-700">Bed Display Name
                                    <span class="text-red-500">*</span></label>
                                <input type="text" name="bed_display_name" id="bed_display_name"
                                    value="{{ old('bed_display_name') }}" required
                                    class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                @error('bed_display_name')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="mb-4">
                                <label for="status"
                                    class="block text-sm font-medium text-gray-700">Status <span
                                        class="text-red-500">*</span></label>
                                <select name="status" id="status" required
                                    class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <option value="available" {{ old('status') == 'available' ? 'selected' : '' }}>
                                        Available</option>
                                    <option value="occupied" {{ old('status') == 'occupied' ? 'selected' : '' }}>Occupied
                                    </option>
                                    <option value="reserved" {{ old('status') == 'reserved' ? 'selected' : '' }}>Reserved
                                    </option>
                                    <option value="maintenance" {{ old('status') == 'maintenance' ? 'selected' : '' }}>
                                        Maintenance</option>
                                </select>
                                @error('status')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="mb-4">
                                <label for="patient_id"
                                    class="block text-sm font-medium text-gray-700">Patient</label>
                                <select name="patient_id" id="patient_id"
                                    class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <option value="">None</option>
                                    @foreach($patients as $patient)
                                        <option value="{{ $patient->id }}" {{ old('patient_id') == $patient->id ? 'selected' : '' }}>{{ $patient->name }}</option>
                                    @endforeach
                                </select>
                                @error('patient_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="mb-4">
                                <label for="nurse_id"
                                    class="block text-sm font-medium text-gray-700">Nurse</label>
                                <select name="nurse_id" id="nurse_id"
                                    class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <option value="">None</option>
                                    @foreach($nurses as $nurse)
                                        <option value="{{ $nurse->id }}" {{ old('nurse_id') == $nurse->id ? 'selected' : '' }}>{{ $nurse->name }}</option>
                                    @endforeach
                                </select>
                                @error('nurse_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="mb-4">
                                <label for="anaesthetist_id"
                                    class="block text-sm font-medium text-gray-700">Anaesthetist
                                    (Optional)</label>
                                <select name="anaesthetist_id" id="anaesthetist_id"
                                    class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <option value="">None</option>
                                    @foreach($anaesthetists as $anaesthetist)
                                        <option value="{{ $anaesthetist->id }}" {{ old('anaesthetist_id') == $anaesthetist->id ? 'selected' : '' }}>{{ $anaesthetist->name }}</option>
                                    @endforeach
                                </select>
                                @error('anaesthetist_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700">Consultants
                                (Multiple)</label>
                            <div
                                class="mt-2 max-h-48 overflow-y-auto border border-gray-300 rounded-md p-2">
                                @foreach($consultants as $consultant)
                                    <div class="flex items-center mb-2">
                                        <input type="checkbox" name="consultant_ids[]" id="consultant_{{ $consultant->id }}"
                                            value="{{ $consultant->id }}" {{ in_array($consultant->id, old('consultant_ids', [])) ? 'checked' : '' }}
                                            class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                        <label for="consultant_{{ $consultant->id }}"
                                            class="ml-2 text-sm text-gray-700">{{ $consultant->name }}</label>
                                    </div>
                                @endforeach
                            </div>
                            @error('consultant_ids')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="flex items-center justify-end gap-4 mt-6">
                            <a href="{{ route('beds.index') }}"
                                class="inline-flex items-center px-4 py-2 bg-gray-100 hover:bg-gray-200 border border-gray-300 rounded-lg font-semibold text-xs text-gray-700 uppercase tracking-widest transition-colors">
                                Cancel
                            </a>
                            <button type="submit"
                                class="inline-flex items-center px-5 py-2.5 bg-gradient-to-r from-blue-600 to-cyan-600 hover:from-blue-700 hover:to-cyan-700 border border-transparent rounded-lg font-semibold text-sm text-white shadow-lg hover:shadow-xl transition-all duration-200">
                                Save
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>