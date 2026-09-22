@php
    // Which tab each field belongs to, so a validation error can open the tab
    // that holds it instead of leaving the message on a hidden panel.
    $tabFields = [
        'profile' => ['personnel_code', 'name', 'registration_number', 'phone', 'email', 'qualification', 'designation', 'years_of_experience'],
        'assignment' => ['ward_id', 'is_tagging', 'tagging_nurse_ids', 'tagging_nurse_ids.*'],
        'access' => ['app_username', 'app_password', 'user_id'],
        'credentialing' => ['credential.*', 'privilege.*', 'checklist.*'],
    ];

    $activeTab = 'profile';
    foreach ($tabFields as $tab => $fields) {
        if ($errors->hasAny($fields)) {
            $activeTab = $tab;
            break;
        }
    }

    // Credentialing saves come back with ?tab=credentialing, and a delete
    // refused for a wrong passphrase with the tab in its old input.
    if (!$errors->any()) {
        $requestedTab = old('active_tab', request('tab'));
        if (is_string($requestedTab) && array_key_exists($requestedTab, $tabFields)) {
            $activeTab = $requestedTab;
        }
    }

    $tabs = [
        'profile' => ['label' => 'Profile', 'hint' => 'Identity and qualifications'],
        'assignment' => ['label' => 'Assignment', 'hint' => 'Ward and tagging'],
        'access' => ['label' => 'Access', 'hint' => 'App login and LDAP'],
        'credentialing' => ['label' => 'Credentialing and Privileging', 'hint' => 'Licences, certificates, privileges'],
    ];

    $field = 'mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500';
    $label = 'block text-sm font-medium text-gray-700';
    $hint = 'mt-1 text-xs text-gray-500';
    $error = 'mt-1 text-sm text-red-600';
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <span class="p-2.5 bg-purple-100 rounded-xl">
                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                </span>
                <div>
                    <h2 class="font-bold text-2xl text-gray-800 leading-tight">{{ $nurse->name }}</h2>
                    <p class="text-sm text-gray-500 mt-0.5">
                        {{ $nurse->designation ?: 'Nurse' }}
                        @if($nurse->registration_number)
                            · Reg. {{ $nurse->registration_number }}
                        @endif
                    </p>
                </div>
                <span
                    class="ml-1 px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full {{ $nurse->is_active ? 'bg-gradient-to-r from-green-100 to-emerald-100 text-green-800 border border-green-200' : 'bg-gradient-to-r from-red-100 to-pink-100 text-red-800 border border-red-200' }}">
                    {{ $nurse->is_active ? 'Active' : 'Inactive' }}
                </span>
            </div>
            <a href="{{ route('nurses.index') }}"
                class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 rounded-lg font-semibold text-sm shadow-sm transition-all">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
                Back to nurses
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
            @if ($errors->any())
                <div class="mb-6 bg-gradient-to-r from-red-50 to-pink-50 border-l-4 border-red-500 text-red-800 px-6 py-4 rounded-lg shadow-md"
                    role="alert">
                    <p class="font-semibold">Some details still need attention</p>
                    <p class="text-sm mt-0.5">The section holding the first problem is open below.</p>
                </div>
            @endif

            <div class="bg-white/90 backdrop-blur-sm shadow-lg rounded-2xl border border-blue-100 overflow-clip"
                x-data="{
                    tab: '{{ $activeTab }}',
                    dirty: false,
                    {{-- The credentialing forms reload the page, which would drop
                         anything typed into the nurse form and not yet saved --}}
                    discardChanges() {
                        return !this.dirty || confirm('You have unsaved changes on the other tabs. Saving here reloads the page and they will be lost. Continue?');
                    },
                }">

                {{-- One long form split into sections: everything stays in the DOM
                     (x-show, not x-if) so a save posts the whole nurse, whichever
                     section happens to be open. Credentialing and Privileging is
                     the exception: it has forms of its own, below this one. --}}
                <nav class="flex flex-wrap gap-1 border-b border-blue-100 bg-blue-50/70 px-3 pt-3"
                    aria-label="Nurse sections">
                    @foreach($tabs as $key => $meta)
                        <button type="button" @click="tab = '{{ $key }}'"
                            :aria-current="tab === '{{ $key }}' ? 'page' : null"
                            class="relative px-5 py-3 rounded-t-xl text-left transition-colors"
                            :class="tab === '{{ $key }}'
                                ? 'bg-white text-blue-700 shadow-sm'
                                : 'text-gray-500 hover:text-gray-700 hover:bg-white/60'">
                            {{-- The panel below is white too, so the active tab needs a
                                 mark of its own to read as selected. --}}
                            <span x-show="tab === '{{ $key }}'"
                                class="absolute inset-x-3 top-0 h-0.5 rounded-full bg-gradient-to-r from-blue-500 to-cyan-500"></span>
                            <span class="block text-sm font-semibold">
                                {{ $meta['label'] }}
                                @if($errors->hasAny($tabFields[$key]))
                                    <span class="ml-1 inline-flex h-2 w-2 rounded-full bg-red-500 align-middle"
                                        title="This section has an error"></span>
                                @elseif($key === 'credentialing' && $credentialing['attention'])
                                    <span class="ml-1 inline-flex h-2 w-2 rounded-full align-middle {{ $credentialing['attention']['level'] === 'red' ? 'bg-red-500' : 'bg-amber-400' }}"
                                        title="{{ ucfirst($credentialing['attention']['text']) }}"></span>
                                @endif
                            </span>
                            <span class="block text-[11px]"
                                :class="tab === '{{ $key }}' ? 'text-blue-400' : 'text-gray-400'">{{ $meta['hint'] }}</span>
                        </button>
                    @endforeach
                </nav>

                <form method="POST" action="{{ route('nurses.update', $nurse) }}" x-show="tab !== 'credentialing'" x-cloak
                    @input="dirty = true" @change="dirty = true">
                    @csrf
                    @method('PUT')

                    <div class="p-6 sm:p-8">
                        {{-- ------------------------------------------------ Profile --}}
                        <div x-show="tab === 'profile'" x-cloak class="space-y-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="name" class="{{ $label }}">Name <span
                                            class="text-red-500">*</span></label>
                                    <input type="text" name="name" id="name" value="{{ old('name', $nurse->name) }}"
                                        required class="{{ $field }}">
                                    @error('name')
                                        <p class="{{ $error }}">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="registration_number" class="{{ $label }}">Registration Number <span
                                            class="text-red-500">*</span></label>
                                    <input type="text" name="registration_number" id="registration_number"
                                        value="{{ old('registration_number', $nurse->registration_number) }}" required
                                        class="{{ $field }}">
                                    @error('registration_number')
                                        <p class="{{ $error }}">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="personnel_code" class="{{ $label }}">Personnel Code (ADT)</label>
                                    <input type="text" name="personnel_code" id="personnel_code"
                                        value="{{ old('personnel_code', $nurse->personnel_code) }}"
                                        placeholder="e.g., NURS001" class="{{ $field }}">
                                    <p class="{{ $hint }}">Code used for ADT/HL7 integration matching</p>
                                    @error('personnel_code')
                                        <p class="{{ $error }}">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="years_of_experience" class="{{ $label }}">Years of Experience</label>
                                    <input type="number" name="years_of_experience" id="years_of_experience"
                                        value="{{ old('years_of_experience', $nurse->years_of_experience) }}" min="0"
                                        class="{{ $field }}">
                                    @error('years_of_experience')
                                        <p class="{{ $error }}">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="phone" class="{{ $label }}">Phone</label>
                                    <input type="text" name="phone" id="phone"
                                        value="{{ old('phone', $nurse->phone) }}" class="{{ $field }}">
                                    @error('phone')
                                        <p class="{{ $error }}">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="email" class="{{ $label }}">Email</label>
                                    <input type="email" name="email" id="email"
                                        value="{{ old('email', $nurse->email) }}" class="{{ $field }}">
                                    @error('email')
                                        <p class="{{ $error }}">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="qualification" class="{{ $label }}">Qualification <span
                                            class="text-red-500">*</span></label>
                                    <select name="qualification" id="qualification" required class="{{ $field }}">
                                        <option value="">Select Qualification</option>
                                        @foreach(['Diploma', 'Degree', 'Masters'] as $qualification)
                                            <option value="{{ $qualification }}" {{ old('qualification', $nurse->qualification) == $qualification ? 'selected' : '' }}>
                                                {{ $qualification }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('qualification')
                                        <p class="{{ $error }}">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="designation" class="{{ $label }}">Designation</label>
                                    <select name="designation" id="designation" class="{{ $field }}">
                                        <option value="">Select Designation (Default:
                                            {{ \App\Models\Nurse::DEFAULT_DESIGNATION }})
                                        </option>
                                        @foreach(\App\Models\Nurse::DESIGNATIONS as $designation)
                                            <option value="{{ $designation }}" {{ old('designation', $nurse->designation) == $designation ? 'selected' : '' }}>
                                                {{ $designation }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('designation')
                                        <p class="{{ $error }}">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        {{-- --------------------------------------------- Assignment --}}
                        <div x-show="tab === 'assignment'" x-cloak class="space-y-6">
                            <div class="md:w-1/2">
                                <label for="ward_id" class="{{ $label }}">Ward</label>
                                <select name="ward_id" id="ward_id" class="{{ $field }}">
                                    <option value="">Select Ward</option>
                                    @foreach($wards as $ward)
                                        <option value="{{ $ward->id }}" {{ old('ward_id', $nurse->ward_id) == $ward->id ? 'selected' : '' }}>
                                            {{ $ward->ward_name }}
                                        </option>
                                    @endforeach
                                </select>
                                <p class="{{ $hint }}">Used to narrow the nurse lists on the ward schedule</p>
                                @error('ward_id')
                                    <p class="{{ $error }}">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="rounded-xl border border-blue-100 bg-blue-50/40 p-5" x-data="{
                                isTagging: {{ old('is_tagging', $nurse->is_tagging) ? 'true' : 'false' }},
                                selectedNurseId: '',
                                selectedNurses: @js(old('tagging_nurse_ids', $nurse->taggingNurses->pluck('id')->toArray())),
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
                                <label class="{{ $label }}">Tagging Nurse</label>
                                <div class="mt-2 flex items-center gap-4">
                                    <label class="inline-flex items-center">
                                        <input type="radio" name="is_tagging" value="0" x-on:change="isTagging = false"
                                            {{ !old('is_tagging', $nurse->is_tagging) ? 'checked' : '' }}
                                            class="text-blue-600 focus:ring-blue-500">
                                        <span class="ml-2 text-gray-700">No</span>
                                    </label>
                                    <label class="inline-flex items-center">
                                        <input type="radio" name="is_tagging" value="1" x-on:change="isTagging = true"
                                            {{ old('is_tagging', $nurse->is_tagging) ? 'checked' : '' }}
                                            class="text-blue-600 focus:ring-blue-500">
                                        <span class="ml-2 text-gray-700">Yes</span>
                                    </label>
                                </div>
                                @error('is_tagging')
                                    <p class="{{ $error }}">{{ $message }}</p>
                                @enderror

                                <div x-show="isTagging" x-cloak class="mt-4">
                                    <label class="{{ $label }} mb-2">Add Tagging Nurse</label>

                                    <div class="flex items-center gap-2">
                                        <select x-model="selectedNurseId"
                                            class="flex-1 rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                            <option value="">Select a nurse to add...</option>
                                            <template x-for="nurse in availableNurses()" :key="nurse.id">
                                                <option :value="nurse.id" x-text="nurse.name"></option>
                                            </template>
                                        </select>
                                        <button type="button" @click="addNurse()" :disabled="!selectedNurseId"
                                            class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-gray-300 disabled:cursor-not-allowed border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest transition">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M12 4v16m8-8H4" />
                                            </svg>
                                            Add
                                        </button>
                                    </div>

                                    <div class="mt-3 space-y-2">
                                        <template x-for="nurseId in selectedNurses" :key="nurseId">
                                            <div
                                                class="flex items-center justify-between bg-white border border-blue-200 rounded-lg px-3 py-2">
                                                <span class="text-sm font-medium text-blue-800"
                                                    x-text="getNurseName(nurseId)"></span>
                                                <button type="button" @click="removeNurse(nurseId)"
                                                    class="text-red-500 hover:text-red-700">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                    </svg>
                                                </button>
                                                <input type="hidden" name="tagging_nurse_ids[]" :value="nurseId">
                                            </div>
                                        </template>
                                        <p x-show="selectedNurses.length === 0" class="text-sm text-gray-500 italic">
                                            No tagging nurses added yet.
                                        </p>
                                    </div>

                                    @error('tagging_nurse_ids')
                                        <p class="{{ $error }}">{{ $message }}</p>
                                    @enderror
                                    @error('tagging_nurse_ids.*')
                                        <p class="{{ $error }}">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        {{-- ------------------------------------------------- Access --}}
                        <div x-show="tab === 'access'" x-cloak class="space-y-6">
                            <div class="rounded-xl border border-cyan-200 bg-cyan-50/50 p-5">
                                <h3 class="text-sm font-semibold text-cyan-800">Nurse App Login</h3>
                                <p class="text-xs text-gray-500 mt-1 mb-4">Credentials used by this nurse to sign in on
                                    the QMed Smart Ward Nurse mobile app.</p>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label for="app_username" class="{{ $label }}">App Username</label>
                                        <input type="text" name="app_username" id="app_username"
                                            value="{{ old('app_username', $nurse->app_username) }}"
                                            placeholder="e.g. srmaria" class="{{ $field }}">
                                        @error('app_username')
                                            <p class="{{ $error }}">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div>
                                        <label for="app_password" class="{{ $label }}">App Password</label>
                                        <input type="password" name="app_password" id="app_password" value=""
                                            autocomplete="new-password" class="{{ $field }}"
                                            placeholder="{{ $nurse->app_password ? 'Leave blank to keep the current password' : 'Set a password to enable app login' }}">
                                        <p class="{{ $hint }}">
                                            @if($nurse->hasAppLogin())
                                                <span class="text-green-600 font-medium">App login is configured.</span>
                                                Leave blank to keep the current password.
                                            @else
                                                App login is not configured yet. Set both username and password to enable
                                                it.
                                            @endif
                                        </p>
                                        @error('app_password')
                                            <p class="{{ $error }}">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            <div class="md:w-2/3">
                                <label for="user_id" class="{{ $label }}">LDAP Binding (Optional)</label>
                                <select name="user_id" id="user_id" class="{{ $field }}">
                                    <option value="">Select User Account</option>
                                    @foreach($users as $user)
                                        <option value="{{ $user->id }}" {{ old('user_id', $nurse->user_id) == $user->id ? 'selected' : '' }}>
                                            {{ $user->name }} ({{ $user->email }})
                                        </option>
                                    @endforeach
                                </select>
                                <p class="{{ $hint }}">Link to an existing user account for LDAP login</p>
                                @error('user_id')
                                    <p class="{{ $error }}">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div
                        class="flex items-center justify-end gap-3 border-t border-blue-100 bg-blue-50/40 px-6 sm:px-8 py-4">
                        <a href="{{ route('nurses.index') }}"
                            class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-lg font-semibold text-sm text-gray-700 hover:bg-gray-50 shadow-sm transition">
                            Cancel
                        </a>
                        <button type="submit"
                            class="inline-flex items-center px-5 py-2 bg-gradient-to-r from-blue-600 to-cyan-600 hover:from-blue-700 hover:to-cyan-700 border border-transparent rounded-lg font-semibold text-sm text-white shadow-lg hover:shadow-xl transition-all">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M5 13l4 4L19 7" />
                            </svg>
                            Update Nurse
                        </button>
                    </div>
                </form>

                {{-- ------------------------------ Credentialing and Privileging --}}
                <div x-show="tab === 'credentialing'" x-cloak>
                    @include('admin.nurses.partials.credentialing')
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
