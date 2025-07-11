<?php

namespace App\Livewire\Permissions;

use Livewire\Component;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\DB;
use Livewire\WithPagination;

class RolePermissions extends Component
{
    use WithPagination;

    public $selectedRole = null;
    public $roles = [];
    public $permissionsByGroup = [];
    public $selectedPermissions = [];
    public $searchTerm = '';
    public $successMessage = '';
    public $errorMessage = '';
    public $selectedGroup = 'all';
    public $availableGroups = [];

    protected $queryString = ['selectedRole', 'selectedGroup', 'searchTerm'];

    public function mount()
    {
        $this->roles = Role::orderBy('name')->get()->toArray();

        if ($this->selectedRole) {
            $this->loadRolePermissions();
        } else if (count($this->roles) > 0) {
            $this->selectedRole = $this->roles[0]['id'];
            $this->loadRolePermissions();
        }

        $this->loadPermissionGroups();
        $this->loadAvailableGroups();
    }

    public function loadRolePermissions()
    {
        if (!$this->selectedRole) {
            return;
        }

        $role = Role::findById($this->selectedRole);
        // Get permissions as a simple array of IDs to avoid model method calls on collections
        $this->selectedPermissions = $role->permissions->pluck('id')->toArray();
    }

    public function loadAvailableGroups()
    {
        // Get all distinct permission groups as a simple array
        $this->availableGroups = Permission::select('group')
            ->whereNotNull('group')
            ->distinct()
            ->orderBy('group')
            ->pluck('group')
            ->toArray();
    }

    public function loadPermissionGroups()
    {
        $query = Permission::query();

        if ($this->searchTerm) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->searchTerm . '%')
                    ->orWhere('description', 'like', '%' . $this->searchTerm . '%');
            });
        }

        if ($this->selectedGroup && $this->selectedGroup !== 'all') {
            $query->where('group', $this->selectedGroup);
        }

        $permissions = $query->orderBy('group')->orderBy('name')->get();

        // Convert permissions to an array format with proper data structure
        $permissionsByGroupArray = [];

        foreach ($permissions as $permission) {
            $group = $permission->group ?? 'ungrouped';

            if (!isset($permissionsByGroupArray[$group])) {
                $permissionsByGroupArray[$group] = [];
            }

            // Convert each permission to a simple array with needed properties
            $permissionArray = [
                'id' => $permission->id,
                'name' => $permission->name,
                'description' => $permission->description ?? '',
                'group' => $permission->group
            ];

            $permissionsByGroupArray[$group][] = $permissionArray;
        }

        $this->permissionsByGroup = $permissionsByGroupArray;
    }

    public function updatedSearchTerm()
    {
        $this->loadPermissionGroups();
    }

    public function updatedSelectedGroup()
    {
        $this->loadPermissionGroups();
    }

    public function updatedSelectedRole()
    {
        $this->loadRolePermissions();
    }

    public function togglePermission($permissionId)
    {
        if (in_array($permissionId, $this->selectedPermissions)) {
            $this->selectedPermissions = array_diff($this->selectedPermissions, [$permissionId]);
        } else {
            $this->selectedPermissions[] = $permissionId;
        }
    }

    public function toggleGroupPermissions($group, $checked)
    {
        if (!isset($this->permissionsByGroup[$group])) {
            return;
        }

        // Extract permission IDs from the group array
        $groupPermissionIds = array_column($this->permissionsByGroup[$group], 'id');

        if ($checked) {
            $this->selectedPermissions = array_unique(array_merge($this->selectedPermissions, $groupPermissionIds));
        } else {
            $this->selectedPermissions = array_diff($this->selectedPermissions, $groupPermissionIds);
        }
    }

    public function savePermissions()
    {
        try {
            DB::beginTransaction();

            $role = Role::findById($this->selectedRole);
            $permissions = Permission::whereIn('id', $this->selectedPermissions)->get();

            $role->syncPermissions($permissions);

            DB::commit();

            $this->successMessage = "Permissions for role '{$role->name}' updated successfully.";
            $this->errorMessage = '';

            $this->dispatch('permissions-updated');
        } catch (\Exception $e) {
            DB::rollBack();
            $this->errorMessage = "Error updating permissions: " . $e->getMessage();
            $this->successMessage = '';
        }
    }

    public function clearMessages()
    {
        $this->successMessage = '';
        $this->errorMessage = '';
    }

    public function isGroupSelected($group)
    {
        if (!isset($this->permissionsByGroup[$group])) {
            return false;
        }

        $groupPermissionIds = array_column($this->permissionsByGroup[$group], 'id');
        $intersection = array_intersect($groupPermissionIds, $this->selectedPermissions);

        return count($intersection) === count($groupPermissionIds);
    }

    public function isGroupPartiallySelected($group)
    {
        if (!isset($this->permissionsByGroup[$group])) {
            return false;
        }

        $groupPermissionIds = array_column($this->permissionsByGroup[$group], 'id');
        $intersection = array_intersect($groupPermissionIds, $this->selectedPermissions);

        return count($intersection) > 0 && count($intersection) < count($groupPermissionIds);
    }

    public function render()
    {
        // Get current role information for display
        $currentRole = null;
        if ($this->selectedRole) {
            $role = Role::find($this->selectedRole);
            if ($role) {
                $currentRole = [
                    'id' => $role->id,
                    'name' => $role->name,
                    'description' => $role->description ?? ''
                ];
            }
        }

        return view('livewire.permissions.role-permissions', [
            'availableGroups' => $this->availableGroups,
            'currentRole' => $currentRole,
        ]);
    }
}
