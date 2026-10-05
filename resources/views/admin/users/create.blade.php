<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-gray-800 leading-tight">
            {{ __('Create User') }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white/90 backdrop-blur-sm overflow-hidden shadow-lg rounded-2xl border border-blue-100">
                <div class="p-8 text-gray-900">
                    <form method="POST" action="{{ route('users.store') }}" x-data="{ role: @js(old('role', App\Models\User::ROLE_HOSPITAL_ADMIN)) }">
                        @csrf

                        <!-- Name -->
                        <div>
                            <x-input-label for="name" :value="__('Name')" />
                            <x-text-input id="name" class="block mt-1 w-full" type="text" name="name"
                                :value="old('name')" required autofocus autocomplete="name" />
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>

                        <!-- Email Address -->
                        <div class="mt-4">
                            <x-input-label for="email" :value="__('Email')" />
                            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email"
                                :value="old('email')" required autocomplete="username" />
                            <x-input-error :messages="$errors->get('email')" class="mt-2" />
                        </div>

                        <!-- Role -->
                        <div class="mt-4">
                            <x-input-label for="role" :value="__('Role')" />
                            <select id="role" name="role" x-model="role"
                                class="block mt-1 w-full border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm">
                                @foreach(App\Models\User::getRoles() as $value => $label)
                                    @if($value !== App\Models\User::ROLE_SUPERADMIN)
                                        <option value="{{ $value }}" {{ old('role') == $value ? 'selected' : '' }}>{{ $label }}
                                        </option>
                                    @endif
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('role')" class="mt-2" />
                            <p x-show="role === @js(App\Models\User::ROLE_INTEGRATION)" x-cloak class="mt-2 text-sm text-gray-600">
                                For a system that calls SmartWard's API, such as the C+ Bed Management RPA. It cannot sign in
                                to SmartWard; after saving you generate its API token on the next page. Any email works,
                                e.g. <span class="font-mono">cplus-rpa@integration.local</span>.
                            </p>
                        </div>

                        <!-- Password (not for Integration Users, who use an API token) -->
                        <div class="mt-4" x-show="role !== @js(App\Models\User::ROLE_INTEGRATION)">
                            <x-input-label for="password" :value="__('Password')" />
                            <x-text-input id="password" class="block mt-1 w-full" type="password" name="password"
                                x-bind:required="role !== @js(App\Models\User::ROLE_INTEGRATION)" autocomplete="new-password" />
                            <x-input-error :messages="$errors->get('password')" class="mt-2" />
                        </div>

                        <!-- Confirm Password -->
                        <div class="mt-4" x-show="role !== @js(App\Models\User::ROLE_INTEGRATION)">
                            <x-input-label for="password_confirmation" :value="__('Confirm Password')" />
                            <x-text-input id="password_confirmation" class="block mt-1 w-full" type="password"
                                name="password_confirmation" x-bind:required="role !== @js(App\Models\User::ROLE_INTEGRATION)" autocomplete="new-password" />
                            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
                        </div>

                        <div class="flex items-center justify-end mt-4">
                            <a href="{{ route('users.index') }}"
                                class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                {{ __('Cancel') }}
                            </a>
                            <x-primary-button class="ml-4">
                                {{ __('Create User') }}
                            </x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>