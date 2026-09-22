<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-gray-800 leading-tight">
            {{ __('Edit Ward') }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white/90 backdrop-blur-sm overflow-hidden shadow-lg rounded-2xl border border-blue-100">
                <div class="p-8 text-gray-900" x-data="{
                    hospitalId: @js((string) old('hospital_id', $ward->hospital_id)),
                    wardTypeId: @js((string) old('ward_type_id', $ward->ward_type_id)),
                    types: @js($wardTypes->map(fn ($t) => ['id' => (string) $t->id, 'hospitalId' => (string) $t->hospital_id])),
                    isAvailable(id) {
                        const type = this.types.find(t => t.id === String(id));
                        return !!type && (type.hospitalId === '' || type.hospitalId === this.hospitalId);
                    },
                }" x-effect="if (wardTypeId && !isAvailable(wardTypeId)) wardTypeId = ''">
                    <form method="POST" action="{{ route('wards.update', $ward) }}">
                        @csrf
                        @method('PUT')

                        <div class="mb-4">
                            <label for="hospital_id" class="block text-sm font-medium text-gray-700">Hospital <span class="text-red-500">*</span></label>
                            <select name="hospital_id" id="hospital_id" required x-model="hospitalId" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Select Hospital</option>
                                @foreach($hospitals as $hospital)
                                    <option value="{{ $hospital->id }}" {{ (old('hospital_id', $ward->hospital_id) == $hospital->id) ? 'selected' : '' }}>{{ $hospital->name }}</option>
                                @endforeach
                            </select>
                            @error('hospital_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="ward_code" class="block text-sm font-medium text-gray-700">Ward Code <span class="text-red-500">*</span></label>
                            <input type="text" name="ward_code" id="ward_code" value="{{ old('ward_code', $ward->ward_code) }}" required class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            @error('ward_code')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="ward_name" class="block text-sm font-medium text-gray-700">Ward Name <span class="text-red-500">*</span></label>
                            <input type="text" name="ward_name" id="ward_name" value="{{ old('ward_name', $ward->ward_name) }}" required class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            @error('ward_name')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="ward_type_id" class="block text-sm font-medium text-gray-700">Ward Type</label>
                            <select name="ward_type_id" id="ward_type_id" x-model="wardTypeId" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">No ward type</option>
                                @foreach($wardTypes as $type)
                                    <option value="{{ $type->id }}" {{ (string) old('ward_type_id', $ward->ward_type_id) === (string) $type->id ? 'selected' : '' }} :hidden="!isAvailable('{{ $type->id }}')" :disabled="!isAvailable('{{ $type->id }}')">{{ $type->name }}{{ $type->hospital_id ? '' : ' (system)' }}</option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-gray-500">Optional. Lists the system ward types plus any this hospital added.</p>
                            @error('ward_type_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="capacity" class="block text-sm font-medium text-gray-700">Capacity <span class="text-red-500">*</span></label>
                            <input type="number" name="capacity" id="capacity" value="{{ old('capacity', $ward->capacity) }}" min="1" required class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            @error('capacity')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="specialties" class="block text-sm font-medium text-gray-700">Specialties</label>
                            <input type="text" name="specialties" id="specialties" value="{{ old('specialties', $ward->specialties) }}" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            @error('specialties')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
                            <textarea name="description" id="description" rows="4" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ old('description', $ward->description) }}</textarea>
                            @error('description')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="flex items-center justify-end gap-4 mt-6">
                            <a href="{{ route('wards.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-100 hover:bg-gray-200 border border-gray-300 rounded-lg font-semibold text-xs text-gray-700 uppercase tracking-widest transition-colors">
                                Cancel
                            </a>
                            <button type="submit" class="inline-flex items-center px-5 py-2.5 bg-gradient-to-r from-blue-600 to-cyan-600 hover:from-blue-700 hover:to-cyan-700 border border-transparent rounded-lg font-semibold text-sm text-white shadow-lg hover:shadow-xl transition-all duration-200">
                                Update
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

