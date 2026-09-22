<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-gray-800 leading-tight">
            {{ __('Users Management') }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white/90 backdrop-blur-sm overflow-hidden shadow-lg rounded-2xl border border-blue-100">
                <div class="p-8 text-gray-900">
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

                    <!-- Search and Filter Form -->
                    <form method="GET" action="{{ route('users.index') }}" class="mb-6">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <!-- Search Input -->
                            <div>
                                <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                                <input type="text" 
                                    name="search" 
                                    id="search"
                                    value="{{ request('search') }}"
                                    placeholder="Search by name or email..."
                                    autocomplete="off"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            </div>

                            <!-- Role Filter -->
                            <div>
                                <label for="role_filter" class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                                <select name="role_filter" 
                                    id="role_filter"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                    <option value="">All Roles</option>
                                    @foreach(App\Models\User::getRoles() as $roleKey => $roleName)
                                        <option value="{{ $roleKey }}" {{ request('role_filter') == $roleKey ? 'selected' : '' }}>
                                            {{ $roleName }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- Filter Buttons -->
                            <div class="flex items-end gap-2">
                                <button type="submit" 
                                    class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    Apply Filters
                                </button>
                                <a href="{{ route('users.index') }}" 
                                    class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-500">
                                    Clear
                                </a>
                            </div>
                        </div>

                        <!-- Hidden inputs to preserve sort parameters -->
                        @if(request('sort'))
                            <input type="hidden" name="sort" value="{{ request('sort') }}">
                        @endif
                        @if(request('direction'))
                            <input type="hidden" name="direction" value="{{ request('direction') }}">
                        @endif
                    </form>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        <a href="{{ route('users.index', array_merge(request()->all(), ['sort' => 'name', 'direction' => request('sort') == 'name' && request('direction') == 'asc' ? 'desc' : 'asc'])) }}" 
                                            class="flex items-center hover:text-gray-700">
                                            Name
                                            @if(request('sort') == 'name')
                                                <span class="ml-1">
                                                    {!! request('direction') == 'asc' ? '↑' : '↓' !!}
                                                </span>
                                            @endif
                                        </a>
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        <a href="{{ route('users.index', array_merge(request()->all(), ['sort' => 'email', 'direction' => request('sort') == 'email' && request('direction') == 'asc' ? 'desc' : 'asc'])) }}" 
                                            class="flex items-center hover:text-gray-700">
                                            Email
                                            @if(request('sort') == 'email')
                                                <span class="ml-1">
                                                    {!! request('direction') == 'asc' ? '↑' : '↓' !!}
                                                </span>
                                            @endif
                                        </a>
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        <a href="{{ route('users.index', array_merge(request()->all(), ['sort' => 'role', 'direction' => request('sort') == 'role' && request('direction') == 'asc' ? 'desc' : 'asc'])) }}" 
                                            class="flex items-center hover:text-gray-700">
                                            Role
                                            @if(request('sort') == 'role')
                                                <span class="ml-1">
                                                    {!! request('direction') == 'asc' ? '↑' : '↓' !!}
                                                </span>
                                            @endif
                                        </a>
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Status
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($users as $user)
                                                            <tr>
                                                                <td class="px-6 py-4 whitespace-nowrap">{{ $user->name }}</td>
                                                                <td class="px-6 py-4 whitespace-nowrap">{{ $user->email }}</td>
                                                                <td class="px-6 py-4 whitespace-nowrap">
                                                                    @if($user->isSuperadmin() || $user->id === auth()->id())
                                                                        <span
                                                                            class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                                                {{ $user->isSuperadmin() ? 'bg-purple-100 text-purple-800' :
                                    ($user->hasRole(App\Models\User::ROLE_HOSPITAL_ADMIN) ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800') }}">
                                                                            {{ App\Models\User::getRoles()[$user->role] ?? $user->role }}
                                                                        </span>
                                                                    @else
                                                                        <select 
                                                                            class="role-select px-2 py-1 text-xs font-semibold rounded-full border focus:ring-2 focus:ring-blue-500 focus:outline-none
                                                                                {{ $user->hasRole(App\Models\User::ROLE_HOSPITAL_ADMIN) ? 'bg-blue-50 border-blue-200' : 'bg-gray-50 border-gray-200' }}"
                                                                            data-user-id="{{ $user->id }}"
                                                                            data-current-role="{{ $user->role }}">
                                                                            @foreach(App\Models\User::getRoles() as $roleKey => $roleName)
                                                                                @if($roleKey !== App\Models\User::ROLE_SUPERADMIN)
                                                                                    <option value="{{ $roleKey }}" {{ $user->role === $roleKey ? 'selected' : '' }}>
                                                                                        {{ $roleName }}
                                                                                    </option>
                                                                                @endif
                                                                            @endforeach
                                                                        </select>
                                                                    @endif
                                                                </td>
                                                                <td class="px-6 py-4 whitespace-nowrap">
                                                                    @if($user->isActive())
                                                                        <span class="inline-flex px-2 text-xs font-semibold leading-5 text-green-800 bg-green-100 rounded-full">
                                                                            Active
                                                                        </span>
                                                                    @else
                                                                        <span class="inline-flex px-2 text-xs font-semibold leading-5 text-red-800 bg-red-100 rounded-full">
                                                                            Deactivated
                                                                        </span>
                                                                        <div class="text-xs text-gray-500 mt-1">
                                                                            {{ $user->deactivated_at->format('d/m/Y H:i') }}
                                                                        </div>
                                                                    @endif
                                                                </td>
                                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                                    <a href="{{ route('users.edit', $user) }}"
                                                                        class="text-indigo-600 hover:text-indigo-900 mr-2">Edit</a>

                                                                    @if(!$user->isSuperadmin() && $user->id !== auth()->id())
                                                                        <form action="{{ route('users.toggle-status', $user) }}" method="POST" class="inline-block mr-2">
                                                                            @csrf
                                                                            <button type="submit" 
                                                                                class="{{ $user->isActive() ? 'text-yellow-600 hover:text-yellow-900' : 'text-green-600 hover:text-green-900' }}">
                                                                                {{ $user->isActive() ? 'Deactivate' : 'Activate' }}
                                                                            </button>
                                                                        </form>

                                                                        <form action="{{ route('users.destroy', $user) }}" method="POST"
                                                                            class="inline-block" >
                                                                            @csrf
                                                                            @method('DELETE')
                                                                            <button type="button" onclick="confirmDelete(event, 'Are you sure you want to delete this item?')"
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

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const roleSelects = document.querySelectorAll('.role-select');
            
            roleSelects.forEach(select => {
                select.addEventListener('change', async function(e) {
                    const userId = this.dataset.userId;
                    const currentRole = this.dataset.currentRole;
                    const newRole = this.value;
                    
                    if (newRole === currentRole) {
                        return; // No change
                    }
                    
                    // Disable select and show loading
                    this.disabled = true;
                    const originalBg = this.className;
                    this.className = this.className.replace(/bg-\w+-\d+/, 'bg-gray-200');
                    
                    try {
                        const response = await fetch(`/users/${userId}/update-role`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({ role: newRole })
                        });
                        
                        const data = await response.json();
                        
                        if (data.success) {
                            // Update the current role
                            this.dataset.currentRole = data.role;
                            
                            // Update styling based on new role
                            if (data.role === 'hospital_admin') {
                                this.className = originalBg.replace(/bg-gray-50 border-gray-200/, 'bg-blue-50 border-blue-200');
                            } else {
                                this.className = originalBg.replace(/bg-blue-50 border-blue-200/, 'bg-gray-50 border-gray-200');
                            }
                            
                            // Show success message
                            showMessage(data.message, 'success');
                        } else {
                            // Revert selection
                            this.value = currentRole;
                            showMessage(data.message, 'error');
                        }
                    } catch (error) {
                        // Revert selection on error
                        this.value = currentRole;
                        showMessage('An error occurred while updating the role.', 'error');
                        console.error('Error:', error);
                    } finally {
                        this.disabled = false;
                    }
                });
            });
            
            function showMessage(message, type) {
                // Create message element
                const messageDiv = document.createElement('div');
                messageDiv.className = `fixed top-4 right-4 px-6 py-3 rounded-lg shadow-lg z-50 ${
                    type === 'success' ? 'bg-green-500 text-white' : 'bg-red-500 text-white'
                }`;
                messageDiv.textContent = message;
                
                document.body.appendChild(messageDiv);
                
                // Auto remove after 3 seconds
                setTimeout(() => {
                    messageDiv.style.opacity = '0';
                    messageDiv.style.transition = 'opacity 0.5s';
                    setTimeout(() => messageDiv.remove(), 500);
                }, 3000);
            }
        });
    </script>
    @endpush
</x-app-layout>