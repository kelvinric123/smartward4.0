<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-gray-800 leading-tight">
            {{ __('Edit Consultant') }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white/90 backdrop-blur-sm overflow-hidden shadow-lg rounded-2xl border border-blue-100">
                <div class="p-8 text-gray-900">
                    <form method="POST" action="{{ route('consultants.update', $consultant) }}">
                        @csrf
                        @method('PUT')

                        <div class="mb-4">
                            <label for="personnel_code" class="block text-sm font-medium text-gray-700">Personnel Code (ADT)</label>
                            <input type="text" name="personnel_code" id="personnel_code" value="{{ old('personnel_code', $consultant->personnel_code) }}" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="e.g., 00017">
                            <p class="mt-1 text-xs text-gray-500">Used for ADT/HL7 integration to identify the consultant</p>
                            @error('personnel_code')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="name" class="block text-sm font-medium text-gray-700">Name <span class="text-red-500">*</span></label>
                            <input type="text" name="name" id="name" value="{{ old('name', $consultant->name) }}" required class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            @error('name')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="specialty_id" class="block text-sm font-medium text-gray-700">Specialty</label>
                            <select name="specialty_id" id="specialty_id" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">No Specialty</option>
                                @foreach($specialties as $specialty)
                                    <option value="{{ $specialty->id }}" {{ old('specialty_id', $consultant->specialty_id) == $specialty->id ? 'selected' : '' }}>{{ $specialty->name }}</option>
                                @endforeach
                            </select>
                            @error('specialty_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="registration_number" class="block text-sm font-medium text-gray-700">Registration Number <span class="text-red-500">*</span></label>
                            <input type="text" name="registration_number" id="registration_number" value="{{ old('registration_number', $consultant->registration_number) }}" required class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            @error('registration_number')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="phone" class="block text-sm font-medium text-gray-700">Phone</label>
                            <input type="text" name="phone" id="phone" value="{{ old('phone', $consultant->phone) }}" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            @error('phone')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                            <input type="email" name="email" id="email" value="{{ old('email', $consultant->email) }}" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            @error('email')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-6 p-4 rounded-lg border border-blue-200 bg-blue-50">
                            <h3 class="text-sm font-semibold text-blue-800 mb-1">Doctor App Login</h3>
                            <p class="text-xs text-gray-500 mb-4">Credentials used by this consultant to sign in on the QMed Smart Ward Doctor mobile app.</p>

                            <div class="mb-4">
                                <label for="app_username" class="block text-sm font-medium text-gray-700">App Username</label>
                                <input type="text" name="app_username" id="app_username" value="{{ old('app_username', $consultant->app_username) }}" autocomplete="off" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="e.g. drrajan">
                                @error('app_username')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="mb-1">
                                <label for="app_password" class="block text-sm font-medium text-gray-700">App Password</label>
                                <input type="password" name="app_password" id="app_password" value="" autocomplete="new-password" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="{{ $consultant->app_password ? 'Leave blank to keep the current password' : 'Set a password to enable app login' }}">
                                <p class="mt-1 text-xs text-gray-500">
                                    @if($consultant->hasAppLogin())
                                        <span class="text-green-600 font-medium">App login is configured.</span> Leave blank to keep the current password.
                                    @else
                                        App login is not configured yet. Set both username and password to enable it.
                                    @endif
                                </p>
                                @error('app_password')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="qualifications" class="block text-sm font-medium text-gray-700">Qualifications</label>
                            <textarea name="qualifications" id="qualifications" rows="3" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ old('qualifications', $consultant->qualifications) }}</textarea>
                            @error('qualifications')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="years_of_experience" class="block text-sm font-medium text-gray-700">Years of Experience</label>
                            <input type="number" name="years_of_experience" id="years_of_experience" value="{{ old('years_of_experience', $consultant->years_of_experience) }}" min="0" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            @error('years_of_experience')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="flex items-center justify-end gap-4 mt-6">
                            <a href="{{ route('consultants.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-100 hover:bg-gray-200 border border-gray-300 rounded-lg font-semibold text-xs text-gray-700 uppercase tracking-widest transition-colors">
                                Cancel
                            </a>
                            <button type="submit" class="inline-flex items-center px-5 py-2.5 bg-gradient-to-r from-brand-600 to-accent-600 hover:from-brand-700 hover:to-accent-700 border border-transparent rounded-lg font-semibold text-sm text-white shadow-lg hover:shadow-xl transition-all duration-200">
                                Update
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

