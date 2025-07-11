<div>
    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Page Header -->
            <div class="mb-8 bg-gradient-to-r from-indigo-500 to-purple-600 rounded-lg shadow-lg p-6">
                <div class="flex flex-col md:flex-row justify-between items-center">
                    <div class="flex items-center mb-4 md:mb-0">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-white mr-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                        <div>
                            <h1 class="text-2xl font-bold text-white">Role Permission Management</h1>
                            <p class="text-indigo-100">Assign and manage permissions for system roles</p>
                        </div>
                    </div>

                    <a href="{{ route('permissions.index') }}" wire:navigate
                        class="inline-flex items-center px-4 py-2 bg-white border border-transparent rounded-md font-semibold text-xs text-indigo-700 uppercase tracking-widest hover:bg-indigo-50 focus:bg-indigo-100 active:bg-indigo-200 focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-indigo-600 transition ease-in-out duration-150 shadow-md">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 17l-5-5m0 0l5-5m-5 5h12" />
                        </svg>
                        Back to Permissions
                    </a>
                </div>
            </div>

            <!-- Notifications -->
            @if ($successMessage)
                <div class="mb-6 bg-green-50 border-l-4 border-green-500 p-4 relative" x-data="{ show: true }" x-show="show">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-green-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-green-700">{{ $successMessage }}</p>
                        </div>
                        <div class="ml-auto pl-3">
                            <div class="-mx-1.5 -my-1.5">
                                <button type="button" @click="show = false" wire:click="clearMessages" class="inline-flex rounded-md p-1.5 text-green-500 hover:bg-green-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                    <span class="sr-only">Dismiss</span>
                                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            @if ($errorMessage)
                <div class="mb-6 bg-red-50 border-l-4 border-red-500 p-4 relative" x-data="{ show: true }" x-show="show">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-red-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-red-700">{{ $errorMessage }}</p>
                        </div>
                        <div class="ml-auto pl-3">
                            <div class="-mx-1.5 -my-1.5">
                                <button type="button" @click="show = false" wire:click="clearMessages" class="inline-flex rounded-md p-1.5 text-red-500 hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                    <span class="sr-only">Dismiss</span>
                                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <div class="flex flex-col md:flex-row gap-6">
                <!-- Role Selection and Filters Sidebar -->
                <div class="w-full md:w-1/3">
                    <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-5">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Role & Filters</h3>

                        <!-- Role Selection -->
                        <div class="mb-6">
                            <label for="role-select" class="block text-sm font-medium text-gray-700 mb-1">Select Role</label>
                            <select id="role-select" wire:model.live="selectedRole" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                                @foreach ($roles as $role)
                                    <option value="{{ $role['id'] }}">{{ ucwords(str_replace('_', ' ', $role['name'])) }}</option>
                                @endforeach
                            </select>
                            
                            @if(isset($currentRole) && $currentRole)
                                <div class="mt-3 p-3 bg-indigo-50 rounded-md">
                                    <p class="text-xs text-indigo-700">
                                        <span class="font-medium">Role ID:</span> #{{ $currentRole['id'] }}
                                    </p>
                                    @if(isset($currentRole['description']) && $currentRole['description'])
                                        <p class="text-xs text-indigo-600 mt-1">{{ $currentRole['description'] }}</p>
                                    @endif
                                    <p class="text-xs text-indigo-700 mt-1">
                                        <span class="font-medium">Total permissions:</span> {{ count($selectedPermissions) }}
                                    </p>
                                </div>
                            @endif
                        </div>

                        <!-- Filter by Group -->
                        <div class="mb-6">
                            <label for="group-filter" class="block text-sm font-medium text-gray-700 mb-1">Filter by Group</label>
                            <select id="group-filter" wire:model.live="selectedGroup" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                                <option value="all">All Groups</option>
                                @foreach ($availableGroups as $group)
                                    <option value="{{ $group }}">{{ ucwords(str_replace('_', ' ', $group)) }}</option>
                                @endforeach
                                <option value="ungrouped">Ungrouped</option>
                            </select>
                        </div>

                        <!-- Search Permissions -->
                        <div class="mb-6">
                            <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search Permissions</label>
                            <div class="mt-1 relative rounded-md shadow-sm">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <input type="text" name="search" id="search" wire:model.live="searchTerm" class="focus:ring-indigo-500 focus:border-indigo-500 block w-full pl-10 pr-3 py-2 sm:text-sm border-gray-300 rounded-md" placeholder="Search permission name...">
                            </div>
                        </div>

                        <!-- Save Button -->
                        <button wire:click="savePermissions" class="w-full inline-flex justify-center items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:border-indigo-900 focus:ring ring-indigo-300 disabled:opacity-25 transition ease-in-out duration-150">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
                            </svg>
                            Save Permission Changes
                        </button>
                    </div>
                </div>

                <!-- Permissions Content -->
                <div class="w-full md:w-2/3">
                    <div class="bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden">
                        <div class="p-5 border-b border-gray-200 bg-gray-50">
                            <h3 class="text-lg font-semibold text-gray-800">Manage Role Permissions</h3>
                            <p class="text-sm text-gray-600 mt-1">Select the permissions you want to assign to this role. Changes will be saved when you click the "Save" button.</p>
                        </div>

                        <div class="divide-y divide-gray-200">
                            @forelse ($permissionsByGroup as $group => $permissions)
                                @php
                                    $isGroupSelected = $this->isGroupSelected($group);
                                    $isPartiallySelected = $this->isGroupPartiallySelected($group);
                                    $displayGroup = $group === 'ungrouped' ? 'Ungrouped' : ucwords(str_replace('_', ' ', $group));
                                    $permissionCount = count($permissions);
                                    
                                    // Determine group color
                                    $bgClass = 'bg-gray-50';
                                    $textClass = 'text-gray-700';
                                    switch($group) {
                                        case 'user_management':
                                            $bgClass = 'bg-blue-50';
                                            $textClass = 'text-blue-700';
                                            break;
                                        case 'financial_operations':
                                            $bgClass = 'bg-green-50';
                                            $textClass = 'text-green-700';
                                            break;
                                        case 'approval_management':
                                            $bgClass = 'bg-purple-50';
                                            $textClass = 'text-purple-700';
                                            break;
                                        case 'data_management':
                                            $bgClass = 'bg-yellow-50';
                                            $textClass = 'text-yellow-700';
                                            break;
                                        case 'branch_management':
                                            $bgClass = 'bg-indigo-50';
                                            $textClass = 'text-indigo-700';
                                            break;
                                        case 'customer_management':
                                            $bgClass = 'bg-sky-50';
                                            $textClass = 'text-sky-700';
                                            break;
                                        case 'safe_management':
                                            $bgClass = 'bg-red-50';
                                            $textClass = 'text-red-700';
                                            break;
                                    }
                                @endphp
                                
                                <div class="p-5">
                                    <div class="flex items-center mb-4">
                                        <input id="group-{{ $group }}" type="checkbox"
                                            class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                                            wire:click="toggleGroupPermissions('{{ $group }}', {{ $isGroupSelected ? 'false' : 'true' }})"
                                            @if ($isGroupSelected) checked @endif
                                            @if ($isPartiallySelected) indeterminate @endif
                                        >
                                        <label for="group-{{ $group }}" class="ml-2 text-md font-medium {{ $textClass }}">
                                            {{ $displayGroup }} ({{ $permissionCount }})
                                        </label>
                                    </div>
                                    
                                    <div class="ml-6 space-y-3">
                                        @foreach ($permissions as $permission)
                                            <div class="flex items-start">
                                                <div class="flex items-center h-5">
                                                    <input id="permission-{{ $permission['id'] }}" type="checkbox"
                                                        class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                                                        wire:click="togglePermission({{ $permission['id'] }})"
                                                        @if (in_array($permission['id'], $selectedPermissions)) checked @endif
                                                    >
                                                </div>
                                                <div class="ml-3 text-sm">
                                                    <label for="permission-{{ $permission['id'] }}" class="font-medium text-gray-700">{{ $permission['name'] }}</label>
                                                    @if(isset($permission['description']) && $permission['description'])
                                                        <p class="text-gray-500">{{ $permission['description'] }}</p>
                                                    @endif
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @empty
                                <div class="p-6 text-center">
                                    <svg class="h-12 w-12 text-gray-400 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    <p class="text-gray-500">No permissions found. Try changing your filters or search term.</p>
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
