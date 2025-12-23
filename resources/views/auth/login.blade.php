<x-guest-layout>
    <div x-data="{ mode: 'selection' }" class="min-h-[400px] flex flex-col justify-center">

        <!-- Header -->
        <div class="mb-8 text-center">
            <h2 class="text-3xl font-extrabold text-gray-800 tracking-tight">
                <span class="bg-clip-text text-transparent bg-gradient-to-r from-blue-600 to-cyan-600">SmartWard</span>
                Login
            </h2>
            <p class="text-sm text-gray-500 mt-2">Select your login method to continue</p>
        </div>

        <!-- Session Status -->
        <x-auth-session-status class="mb-4" :status="session('status')" />

        <!-- Selection Mode -->
        <div x-show="mode === 'selection'" x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 transform scale-95"
            x-transition:enter-end="opacity-100 transform scale-100" class="space-y-4">

            <button @click="mode = 'email'"
                class="w-full group relative p-6 bg-white border-2 border-slate-100 rounded-2xl hover:border-blue-500 hover:shadow-xl transition-all duration-300 text-left">
                <div class="flex items-center">
                    <div
                        class="flex-shrink-0 p-3 bg-blue-50 rounded-xl group-hover:bg-blue-600 transition-colors duration-300">
                        <svg class="w-8 h-8 text-blue-600 group-hover:text-white transition-colors duration-300"
                            fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                        </svg>
                    </div>
                    <div class="ml-5">
                        <h3 class="text-lg font-bold text-gray-800 group-hover:text-blue-600 transition-colors">Standard
                            Login</h3>
                        <p class="text-sm text-gray-500 mt-1">For Admin & Non-LDAP Users with password</p>
                    </div>
                    <div class="ml-auto opacity-0 group-hover:opacity-100 transition-opacity">
                        <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </div>
                </div>
            </button>

            <a href="{{ route('login.ldap') }}"
                class="block w-full group relative p-6 bg-white border-2 border-slate-100 rounded-2xl hover:border-emerald-500 hover:shadow-xl transition-all duration-300 text-left">
                <div class="flex items-center">
                    <div
                        class="flex-shrink-0 p-3 bg-emerald-50 rounded-xl group-hover:bg-emerald-600 transition-colors duration-300">
                        <svg class="w-8 h-8 text-emerald-600 group-hover:text-white transition-colors duration-300"
                            fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                    </div>
                    <div class="ml-5">
                        <h3 class="text-lg font-bold text-gray-800 group-hover:text-emerald-600 transition-colors">LDAP
                            User Login</h3>
                        <p class="text-sm text-gray-500 mt-1">Select your user account (No password required)</p>
                    </div>
                    <div class="ml-auto opacity-0 group-hover:opacity-100 transition-opacity">
                        <svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </div>
                </div>
            </a>
        </div>

        <!-- Standard Login Form -->
        <div x-show="mode === 'email'" x-cloak x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-x-4" x-transition:enter-end="opacity-100 translate-x-0">

            <button @click="mode = 'selection'"
                class="mb-6 flex items-center text-sm text-gray-500 hover:text-blue-600 transition-colors">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
                Back to selection
            </button>

            <form method="POST" action="{{ route('login') }}" class="space-y-5">
                @csrf
                <!-- Email Address -->
                <div>
                    <x-input-label for="email" :value="__('Email Address')" class="text-gray-700 font-medium" />
                    <div class="relative mt-2">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207" />
                            </svg>
                        </div>
                        <x-text-input id="email"
                            class="block w-full pl-10 pr-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                            type="email" name="email" :value="old('email')" required autofocus autocomplete="username"
                            placeholder="Enter your email" />
                    </div>
                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
                </div>

                <!-- Password -->
                <div>
                    <x-input-label for="password" :value="__('Password')" class="text-gray-700 font-medium" />
                    <div class="relative mt-2">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                        </div>
                        <x-text-input id="password"
                            class="block w-full pl-10 pr-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                            type="password" name="password" required autocomplete="current-password"
                            placeholder="Enter your password" />
                    </div>
                    <x-input-error :messages="$errors->get('password')" class="mt-2" />
                </div>

                <!-- Remember Me & Forgot Password -->
                <div class="flex items-center justify-between">
                    <label for="remember_me" class="inline-flex items-center">
                        <input id="remember_me" type="checkbox"
                            class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500" name="remember">
                        <span class="ml-2 text-sm text-gray-600">{{ __('Remember me') }}</span>
                    </label>

                    @if (Route::has('password.request'))
                        <a class="text-sm text-blue-600 hover:text-blue-800 font-medium transition-colors"
                            href="{{ route('password.request') }}">
                            {{ __('Forgot password?') }}
                        </a>
                    @endif
                </div>

                <div class="pt-2">
                    <button type="submit"
                        class="w-full bg-gradient-to-r from-blue-600 to-cyan-600 hover:from-blue-700 hover:to-cyan-700 text-white font-semibold py-3 px-4 rounded-lg shadow-lg hover:shadow-xl transition-all duration-200 transform hover:-translate-y-0.5">
                        {{ __('Sign In with Password') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-guest-layout>