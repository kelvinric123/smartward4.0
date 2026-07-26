<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Add Nurse') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <form method="POST" action="{{ route('nurses.store') }}">
                        @csrf

                        <div class="mb-4">
                            <label for="personnel_code"
                                class="block text-sm font-medium text-gray-700 dark:text-gray-300">Personnel Code
                                (ADT)</label>
                            <input type="text" name="personnel_code" id="personnel_code"
                                value="{{ old('personnel_code') }}" placeholder="e.g., NURS001"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Code used for ADT/HL7 integration
                                matching</p>
                            @error('personnel_code')
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Name
                                <span class="text-red-500">*</span></label>
                            <input type="text" name="name" id="name" value="{{ old('name') }}" required
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @error('name')
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="registration_number"
                                class="block text-sm font-medium text-gray-700 dark:text-gray-300">Registration Number
                                <span class="text-red-500">*</span></label>
                            <input type="text" name="registration_number" id="registration_number"
                                value="{{ old('registration_number') }}" required
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @error('registration_number')
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="phone"
                                class="block text-sm font-medium text-gray-700 dark:text-gray-300">Phone</label>
                            <input type="text" name="phone" id="phone" value="{{ old('phone') }}"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @error('phone')
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="email"
                                class="block text-sm font-medium text-gray-700 dark:text-gray-300">Email</label>
                            <input type="email" name="email" id="email" value="{{ old('email') }}"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @error('email')
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-6 p-4 rounded-lg border border-cyan-200 dark:border-cyan-800 bg-cyan-50 dark:bg-cyan-900/20">
                            <h3 class="text-sm font-semibold text-cyan-800 dark:text-cyan-200 mb-1">Nurse App Login</h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">Credentials used by this nurse to sign in on the QMed Smart Ward Nurse mobile app. Optional — can be configured later.</p>

                            <div class="mb-4">
                                <label for="app_username" class="block text-sm font-medium text-gray-700 dark:text-gray-300">App Username</label>
                                <input type="text" name="app_username" id="app_username" value="{{ old('app_username') }}" autocomplete="off" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="e.g. srmaria">
                                @error('app_username')
                                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="mb-1">
                                <label for="app_password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">App Password</label>
                                <input type="password" name="app_password" id="app_password" value="" autocomplete="new-password" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Set a password to enable app login">
                                @error('app_password')
                                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="qualification"
                                class="block text-sm font-medium text-gray-700 dark:text-gray-300">Qualification <span
                                    class="text-red-500">*</span></label>
                            <select name="qualification" id="qualification" required
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">Select Qualification</option>
                                <option value="Diploma" {{ old('qualification') == 'Diploma' ? 'selected' : '' }}>Diploma
                                </option>
                                <option value="Degree" {{ old('qualification') == 'Degree' ? 'selected' : '' }}>Degree
                                </option>
                                <option value="Masters" {{ old('qualification') == 'Masters' ? 'selected' : '' }}>Masters
                                </option>
                            </select>
                            @error('qualification')
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="designation"
                                class="block text-sm font-medium text-gray-700 dark:text-gray-300">Designation</label>
                            <select name="designation" id="designation"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">Select Designation (Default:
                                    {{ \App\Models\Nurse::DEFAULT_DESIGNATION }})
                                </option>
                                @foreach(\App\Models\Nurse::DESIGNATIONS as $designation)
                                    <option value="{{ $designation }}" {{ old('designation') == $designation ? 'selected' : '' }}>{{ $designation }}</option>
                                @endforeach
                            </select>
                            @error('designation')
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="ward_id"
                                class="block text-sm font-medium text-gray-700 dark:text-gray-300">Ward</label>
                            <select name="ward_id" id="ward_id"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">Select Ward</option>
                                @foreach($wards as $ward)
                                    <option value="{{ $ward->id }}" {{ old('ward_id') == $ward->id ? 'selected' : '' }}>
                                        {{ $ward->ward_name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('ward_id')
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-4" x-data="{
                            isTagging: {{ old('is_tagging', false) ? 'true' : 'false' }},
                            selectedNurseId: '',
                            selectedNurses: @js(old('tagging_nurse_ids', [])),
                            allNurses: @js($nurses->map(fn($n) => ['id' => $n->id, 'name' => $n->name])),
                            addNurse() {
                                if (!this.selectedNurseId) return;
                                const id = parseInt(this.selectedNurseId);
                                if (!this.selectedNurses.includes(id)) {
                                    this.selectedNurses.push(id);
                                }
                                this.selectedNurseId = '';
                            },
                            removeNurse(id) {
                                this.selectedNurses = this.selectedNurses.filter(n => n !== id);
                            },
                            getNurseName(id) {
                                const nurse = this.allNurses.find(n => n.id === id);
                                return nurse ? nurse.name : 'Unknown';
                            },
                            availableNurses() {
                                return this.allNurses.filter(n => !this.selectedNurses.includes(n.id));
                            }
                        }">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Tagging Nurse</label>
                            <div class="mt-2 flex items-center gap-4">
                                <label class="inline-flex items-center">
                                    <input type="radio" name="is_tagging" value="0" x-on:change="isTagging = false"
                                        {{ !old('is_tagging', false) ? 'checked' : '' }}
                                        class="form-radio text-indigo-600 focus:ring-indigo-500 dark:bg-gray-900 dark:border-gray-700">
                                    <span class="ml-2 text-gray-700 dark:text-gray-300">No</span>
                                </label>
                                <label class="inline-flex items-center">
                                    <input type="radio" name="is_tagging" value="1" x-on:change="isTagging = true"
                                        {{ old('is_tagging', false) ? 'checked' : '' }}
                                        class="form-radio text-indigo-600 focus:ring-indigo-500 dark:bg-gray-900 dark:border-gray-700">
                                    <span class="ml-2 text-gray-700 dark:text-gray-300">Yes</span>
                                </label>
                            </div>
                            @error('is_tagging')
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror

                            <div x-show="isTagging" x-cloak class="mt-4">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Add Tagging Nurse</label>
                                
                                <!-- Add Nurse Row -->
                                <div class="flex items-center gap-2">
                                    <select x-model="selectedNurseId"
                                        class="flex-1 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <option value="">Select a nurse to add...</option>
                                        <template x-for="nurse in availableNurses()" :key="nurse.id">
                                            <option :value="nurse.id" x-text="nurse.name"></option>
                                        </template>
                                    </select>
                                    <button type="button" @click="addNurse()" :disabled="!selectedNurseId"
                                        class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 disabled:bg-gray-300 disabled:cursor-not-allowed border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest transition ease-in-out duration-150">
                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                        </svg>
                                        Add
                                    </button>
                                </div>

                                <!-- Selected Nurses List -->
                                <div class="mt-3 space-y-2">
                                    <template x-for="nurseId in selectedNurses" :key="nurseId">
                                        <div class="flex items-center justify-between bg-indigo-50 dark:bg-indigo-900/30 border border-indigo-200 dark:border-indigo-700 rounded-lg px-3 py-2">
                                            <span class="text-sm font-medium text-indigo-800 dark:text-indigo-200" x-text="getNurseName(nurseId)"></span>
                                            <button type="button" @click="removeNurse(nurseId)"
                                                class="text-red-500 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                </svg>
                                            </button>
                                            <input type="hidden" name="tagging_nurse_ids[]" :value="nurseId">
                                        </div>
                                    </template>
                                    <p x-show="selectedNurses.length === 0" class="text-sm text-gray-500 dark:text-gray-400 italic">
                                        No tagging nurses added yet.
                                    </p>
                                </div>

                                @error('tagging_nurse_ids')
                                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                                @error('tagging_nurse_ids.*')
                                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="user_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">LDAP
                                Binding (Optional)</label>
                            <select name="user_id" id="user_id"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">Select User Account</option>
                                @foreach($users as $user)
                                    <option value="{{ $user->id }}" {{ old('user_id') == $user->id ? 'selected' : '' }}>
                                        {{ $user->name }} ({{ $user->email }})
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Link to an existing user account
                                for LDAP login</p>
                            @error('user_id')
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="years_of_experience"
                                class="block text-sm font-medium text-gray-700 dark:text-gray-300">Years of
                                Experience</label>
                            <input type="number" name="years_of_experience" id="years_of_experience"
                                value="{{ old('years_of_experience', 0) }}" min="0"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @error('years_of_experience')
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="flex items-center justify-end gap-4 mt-6">
                            <a href="{{ route('nurses.index') }}"
                                class="inline-flex items-center px-4 py-2 bg-gray-300 dark:bg-gray-700 border border-transparent rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-400 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                                Cancel
                            </a>
                            <button type="submit"
                                class="inline-flex items-center px-4 py-2 bg-blue-600 dark:bg-blue-500 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 dark:hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                                Save
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>