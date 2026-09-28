<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-bold text-2xl text-gray-800 leading-tight">
                    {{ __('Create Command Centre') }}
                </h2>
                <p class="text-sm text-gray-500 mt-1">Set up a new Patient Flow Command Centre</p>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white/90 backdrop-blur-sm overflow-hidden shadow-lg rounded-2xl border border-blue-100">
                <div class="p-8">
                    <form method="POST" action="{{ route('patient-flow-command-centres.store') }}">
                        @csrf

                        <div class="mb-6">
                            <label for="name" class="block text-sm font-semibold text-gray-700 mb-1">Name <span class="text-red-500">*</span></label>
                            <input type="text" name="name" id="name" value="{{ old('name') }}" required
                                class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 transition-colors"
                                placeholder="e.g. Main Building Command Centre">
                            @error('name')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-6">
                            <label for="description" class="block text-sm font-semibold text-gray-700 mb-1">Description</label>
                            <textarea name="description" id="description" rows="3"
                                class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 transition-colors"
                                placeholder="Brief description of this command centre">{{ old('description') }}</textarea>
                            @error('description')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="border-t border-gray-200 my-6 pt-6">
                            <h3 class="text-sm font-bold text-gray-800 uppercase tracking-wider mb-4 flex items-center">
                                <svg class="w-4 h-4 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                                </svg>
                                Dashboard Login Credentials
                            </h3>
                            <p class="text-xs text-gray-500 mb-4">These credentials are used to login to the standalone command centre dashboard view</p>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label for="login_username" class="block text-sm font-semibold text-gray-700 mb-1">Username <span class="text-red-500">*</span></label>
                                    <input type="text" name="login_username" id="login_username" value="{{ old('login_username') }}" required
                                        class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 transition-colors"
                                        placeholder="e.g. cc_main" autocomplete="off">
                                    @error('login_username')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div>
                                    <label for="login_password" class="block text-sm font-semibold text-gray-700 mb-1">Password <span class="text-red-500">*</span></label>
                                    <input type="password" name="login_password" id="login_password" required
                                        class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 transition-colors"
                                        placeholder="Min 6 characters" autocomplete="new-password">
                                    @error('login_password')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="mb-6">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Assign Wards <span class="text-red-500">*</span></label>
                            <p class="text-xs text-gray-500 mb-3">Select one or more wards to monitor in this command centre</p>
                            @error('ward_ids')
                                <p class="mb-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 max-h-64 overflow-y-auto p-4 bg-gray-50 rounded-lg border border-gray-200">
                                @forelse($wards as $ward)
                                    <label class="flex items-center p-3 rounded-lg border border-gray-200 bg-white hover:bg-blue-50 hover:border-blue-300 transition-colors cursor-pointer">
                                        <input type="checkbox" name="ward_ids[]" value="{{ $ward->id }}"
                                            {{ in_array($ward->id, old('ward_ids', [])) ? 'checked' : '' }}
                                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 mr-3">
                                        <div>
                                            <span class="text-sm font-medium text-gray-800">{{ $ward->ward_name }}</span>
                                            @if($ward->ward_code)
                                                <span class="text-xs text-gray-500 ml-1">({{ $ward->ward_code }})</span>
                                            @endif
                                        </div>
                                    </label>
                                @empty
                                    <p class="text-sm text-gray-500 col-span-2 text-center py-4">No active wards available. Please create wards first.</p>
                                @endforelse
                            </div>
                        </div>

                        <div class="flex items-center justify-end gap-4 mt-8 pt-6 border-t border-gray-200">
                            <a href="{{ route('patient-flow-command-centres.index') }}"
                                class="inline-flex items-center px-5 py-2.5 bg-gray-100 hover:bg-gray-200 border border-gray-300 rounded-lg font-semibold text-sm text-gray-700 transition-all duration-200">
                                Cancel
                            </a>
                            <button type="submit"
                                class="inline-flex items-center px-5 py-2.5 bg-gradient-to-r from-brand-600 to-accent-600 hover:from-brand-700 hover:to-accent-700 border border-transparent rounded-lg font-semibold text-sm text-white shadow-lg hover:shadow-xl transition-all duration-200">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                Create Command Centre
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
