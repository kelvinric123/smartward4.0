<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Users Management') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-medium">Users</h3>
                        <a href="{{ route('users.create') }}"
                            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Add User</a>
                    </div>

                    @if(session('success'))
                        <div class="mb-4 text-sm text-green-600">
                            {{ session('success') }}
                        </div>
                    @endif
                    @if(session('error'))
                        <div class="mb-4 text-sm text-red-600">
                            {{ session('error') }}
                        </div>
                    @endif

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Name</th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Email</th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Role</th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($users as $user)
                                                            <tr>
                                                                <td class="px-6 py-4 whitespace-nowrap">{{ $user->name }}</td>
                                                                <td class="px-6 py-4 whitespace-nowrap">{{ $user->email }}</td>
                                                                <td class="px-6 py-4 whitespace-nowrap">
                                                                    <span
                                                                        class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                                            {{ $user->isSuperadmin() ? 'bg-purple-100 text-purple-800' :
                                    ($user->hasRole(App\Models\User::ROLE_HOSPITAL_ADMIN) ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800') }}">
                                                                        {{ App\Models\User::getRoles()[$user->role] ?? $user->role }}
                                                                    </span>
                                                                </td>
                                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                                    <a href="{{ route('users.edit', $user) }}"
                                                                        class="text-indigo-600 hover:text-indigo-900 mr-2">Edit</a>

                                                                    @if(!$user->isSuperadmin() && $user->id !== auth()->id())
                                                                        <form action="{{ route('users.destroy', $user) }}" method="POST"
                                                                            class="inline-block" onsubmit="return confirm('Are you sure?')">
                                                                            @csrf
                                                                            @method('DELETE')
                                                                            <button type="submit"
                                                                                class="text-red-600 hover:text-red-900">Delete</button>
                                                                        </form>
                                                                    @endif
                                                                </td>
                                                            </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4">
                        {{ $users->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>