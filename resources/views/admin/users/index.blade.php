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