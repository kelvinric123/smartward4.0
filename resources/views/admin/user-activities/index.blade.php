<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Application Logs') }}
        </h2>
    </x-slot>

    <div class="py-12" x-data="{ activeTab: 'user_activities' }">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">

                    <!-- Tabs -->
                    <div class="mb-6 border-b border-gray-200 flex justify-between items-center">
                        <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                            <button @click="activeTab = 'user_activities'"
                                :class="activeTab === 'user_activities' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                                class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                                User Activities
                            </button>

                            <button @click="activeTab = 'config_changes'"
                                :class="activeTab === 'config_changes' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                                class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                                Config Changes
                            </button>
                        </nav>

                        <a :href="'{{ route('user-activities.export') }}?tab=' + activeTab"
                            class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-500 active:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition ease-in-out duration-150">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            Export to Excel
                        </a>
                    </div>

                    <!-- User Activities Content -->
                    <div x-show="activeTab === 'user_activities'" x-cloak>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            User</th>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Activity</th>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Description</th>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            IP Address</th>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Time</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @forelse($activities->where('activity_type', '!=', 'config_created')->where('activity_type', '!=', 'config_updated')->where('activity_type', '!=', 'config_deleted') as $activity)
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900">
                                                    {{ $activity->user ? $activity->user->name : 'Unknown' }}
                                                </div>
                                                <div class="text-sm text-gray-500">
                                                    {{ $activity->user ? $activity->user->email : '' }}
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span
                                                    class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                                            {{ $activity->activity_type == 'login' ? 'bg-green-100 text-green-800' : '' }}
                                                            {{ $activity->activity_type == 'logout' ? 'bg-gray-100 text-gray-800' : '' }}
                                                            {{ $activity->activity_type == 'password_change' ? 'bg-yellow-100 text-yellow-800' : '' }}
                                                            {{ $activity->activity_type == 'password_reset' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800' }}">
                                                    {{ ucfirst(str_replace('_', ' ', $activity->activity_type)) }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 text-sm text-gray-500">
                                                {{ $activity->description }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {{ $activity->ip_address }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {{ $activity->created_at->format('d/m/Y H:i:s') }}
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5"
                                                class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-500">
                                                No user activities found.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Config Changes Content -->
                    <div x-show="activeTab === 'config_changes'" x-cloak>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            User</th>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Action</th>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Description</th>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Changes</th>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Time</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @forelse($activities->whereIn('activity_type', ['config_created', 'config_updated', 'config_deleted']) as $activity)
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900">
                                                    {{ $activity->user ? $activity->user->name : 'System' }}
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span
                                                    class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                                            {{ $activity->activity_type == 'config_created' ? 'bg-green-100 text-green-800' : '' }}
                                                            {{ $activity->activity_type == 'config_updated' ? 'bg-blue-100 text-blue-800' : '' }}
                                                            {{ $activity->activity_type == 'config_deleted' ? 'bg-red-100 text-red-800' : '' }}">
                                                    {{ ucfirst(str_replace('config_', '', $activity->activity_type)) }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 text-sm text-gray-500">
                                                {{ $activity->description }}
                                            </td>
                                            <td class="px-6 py-4 text-sm text-gray-500">
                                                <div x-data="{ open: false }">
                                                    <button @click="open = !open"
                                                        class="text-indigo-600 hover:text-indigo-900 text-xs">
                                                        <span x-text="open ? 'Hide Details' : 'View Details'"></span>
                                                    </button>
                                                    <div x-show="open"
                                                        class="mt-2 text-xs bg-gray-50 p-2 rounded border border-gray-200 font-mono whitespace-pre-wrap max-w-xs sm:max-w-md overflow-x-auto">
                                                        {{ json_encode($activity->properties, JSON_PRETTY_PRINT) }}
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {{ $activity->created_at->format('d/m/Y H:i:s') }}
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5"
                                                class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-500">
                                                No config changes found.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="mt-4">
                        {{ $activities->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>