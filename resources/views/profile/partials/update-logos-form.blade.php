<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
            {{ __('Application setting and logos') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            {{ __('Update the application name, description, and logos. These will be used on the login page and the left navigation bar.') }}
        </p>
    </header>

    <form method="post" action="{{ route('profile.logos.update') }}" enctype="multipart/form-data" class="mt-6 space-y-6">
        @csrf

        @php
            $hospital = \App\Models\Hospital::first();
            $loginLogoUrl = $hospital && $hospital->login_logo_path ? \Illuminate\Support\Facades\Storage::url($hospital->login_logo_path) : ($hospital && $hospital->logo_path ? \Illuminate\Support\Facades\Storage::url($hospital->logo_path) : asset('phkl_new.png'));
            $navbarLogoUrl = $hospital && $hospital->navbar_logo_path ? \Illuminate\Support\Facades\Storage::url($hospital->navbar_logo_path) : ($hospital && $hospital->logo_path ? \Illuminate\Support\Facades\Storage::url($hospital->logo_path) : asset('phkl_new.png'));
        @endphp

        <div>
            <x-input-label for="app_name" :value="__('App Name')" />
            <x-text-input id="app_name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $hospital ? $hospital->name : 'PHKL Hospital')" required autofocus autocomplete="name" />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        <div>
            <x-input-label for="app_description" :value="__('App Description')" />
            <x-text-input id="app_description" name="description" type="text" class="mt-1 block w-full" :value="old('description', $hospital ? $hospital->description : 'Management System')" autocomplete="description" />
            <x-input-error class="mt-2" :messages="$errors->get('description')" />
        </div>

        <div>
            <x-input-label for="login_logo" :value="__('Login Page Logo')" />
            
            <div class="mt-2 mb-2">
                <img src="{{ $loginLogoUrl }}" alt="Current Login Logo" class="h-20 w-auto object-contain bg-gray-100 p-2 rounded-md border border-gray-300 dark:border-gray-700 dark:bg-gray-800">
            </div>

            <x-text-input id="login_logo" name="login_logo" type="file" class="mt-1 block w-full" accept="image/*" />
            <x-input-error :messages="$errors->get('login_logo')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="navbar_logo" :value="__('Navigation Bar Logo')" />
            
            <div class="mt-2 mb-2">
                <img src="{{ $navbarLogoUrl }}" alt="Current Navbar Logo" class="h-10 w-auto object-contain bg-gray-100 p-2 rounded-md border border-gray-300 dark:border-gray-700 dark:bg-gray-800">
            </div>

            <x-text-input id="navbar_logo" name="navbar_logo" type="file" class="mt-1 block w-full" accept="image/*" />
            <x-input-error :messages="$errors->get('navbar_logo')" class="mt-2" />
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Save') }}</x-primary-button>

            @if (session('status') === 'logos-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm text-gray-600 dark:text-gray-400"
                >{{ __('Saved.') }}</p>
            @endif
        </div>
    </form>
</section>
