<x-guest-layout>
    <div class="mb-6 text-center">
        <h2 class="text-2xl font-bold text-gray-800">LDAP Login</h2>
        <p class="text-sm text-gray-500 mt-1">Select your account to sign in</p>
    </div>

    @if (session('error'))
        <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
            <p>{{ session('error') }}</p>
        </div>
    @endif

    <div class="grid grid-cols-1 gap-4 max-h-[60vh] overflow-y-auto pr-2">
        @forelse ($users as $user)
            <form method="POST" action="{{ route('login.ldap.submit') }}">
                @csrf
                <input type="hidden" name="user_id" value="{{ $user->id }}">
                <button type="submit"
                    class="w-full flex items-center p-4 bg-white border border-gray-200 rounded-xl hover:border-blue-500 hover:shadow-md transition-all group group-hover:bg-blue-50">
                    <div
                        class="w-10 h-10 rounded-full bg-gradient-to-br from-blue-500 to-indigo-600 text-white flex items-center justify-center font-bold text-lg mr-4 group-hover:scale-110 transition-transform">
                        {{ substr($user->name, 0, 1) }}
                    </div>
                    <div class="text-left">
                        <p class="font-semibold text-gray-800 group-hover:text-blue-700">{{ $user->name }}</p>
                        <p class="text-sm text-gray-500">{{ $user->email }}</p>
                        <span
                            class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800 mt-1">
                            {{ $user->role }}
                        </span>
                    </div>
                    <div class="ml-auto">
                        <svg class="w-5 h-5 text-gray-400 group-hover:text-blue-500" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </div>
                </button>
            </form>
        @empty
            <div class="text-center py-8">
                <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>
                <p class="text-gray-500">No LDAP users found.</p>
                <p class="text-sm text-gray-400 mt-1">Please sync users from the dashboard first.</p>
            </div>
        @endforelse
    </div>

    <div class="mt-6 text-center">
        <a href="{{ route('login') }}" class="text-sm text-blue-600 hover:text-blue-800 font-medium">
            &larr; Back to Email Login
        </a>
    </div>
</x-guest-layout>