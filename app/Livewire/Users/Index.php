<?php

namespace App\Livewire\Users;

use Livewire\Component;
use App\Domain\Entities\User;
use Spatie\Permission\Models\Role;
use Livewire\Attributes\On;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Cache;
use App\Models\Domain\Entities\Branch;
use Illuminate\Support\Facades\Hash;

class Index extends Component
{
    use WithPagination;

    public $roles;
    public $branches;
    public $editingUserId = null;
    public $selectedRole = null;
    public $name = '';
    public $role = '';
    public $branchId = '';
    public $showTrashed = false;
    public $confirmingUserDeletion = false;
    public $confirmingUserRestore = false;
    public $userBeingDeleted = null;
    public $userBeingRestored = null;

    protected $queryString = [
        'name' => ['except' => ''],
        'role' => ['except' => ''],
        'branchId' => ['except' => ''],
        'showTrashed' => ['except' => false],
    ];

    public function mount()
    {
        // Temporarily commenting out Gate authorization
        // Gate::authorize('manage-users');

        // Create admin role if it doesn't exist
        $adminRole = Role::where('name', 'admin')->first();
        if (!$adminRole) {
            $adminRole = Role::create(['name' => 'admin']);

            // Add all permissions to admin role
            $permissions = \Spatie\Permission\Models\Permission::all();
            $adminRole->syncPermissions($permissions);
        }

        // Create or update admin user
        $admin = User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        // Assign admin role
        $admin->assignRole('admin');

        $this->roles = Role::all();
        $this->branches = Branch::orderBy('name')->get();
    }

    public function filter()
    {
        $this->resetPage();
    }

    public function resetFilters()
    {
        $this->reset(['name', 'role', 'branchId', 'showTrashed']);
        $this->resetPage();
    }

    #[On('user-role-updated')]
    public function refreshUsers()
    {
        // Just refresh the component
    }

    public function editRole(int $userId)
    {
        $this->editingUserId = $userId;
        $user = User::find($userId);
        $this->selectedRole = $user->getRoleNames()->first();
    }

    public function saveRole()
    {
        // Temporarily commenting out Gate authorization
        // Gate::authorize('manage-users');
        $user = User::find($this->editingUserId);
        if ($user && $this->selectedRole) {
            $user->syncRoles([$this->selectedRole]);
            Cache::forget('spatie.permission.cache'); // Clear permission cache
            session()->flash('message', 'User role updated successfully.');
            $this->cancelEdit();
        }
    }

    public function cancelEdit()
    {
        $this->editingUserId = null;
        $this->selectedRole = null;
    }

    public function confirmUserDeletion($userId)
    {
        $this->userBeingDeleted = User::find($userId);
        $this->confirmingUserDeletion = true;
    }

    public function deleteUser()
    {
        if ($this->userBeingDeleted) {
            Gate::authorize('delete', $this->userBeingDeleted);
            $this->userBeingDeleted->delete();
            session()->flash('message', 'User deleted successfully.');
            $this->confirmingUserDeletion = false;
            $this->userBeingDeleted = null;
        }
    }

    public function confirmRestore($userId)
    {
        $this->userBeingRestored = User::withTrashed()->find($userId);
        $this->confirmingUserRestore = true;
    }

    public function restoreUser()
    {
        if ($this->userBeingRestored) {
            Gate::authorize('restore', $this->userBeingRestored);
            $this->userBeingRestored->restore();
            session()->flash('message', 'User restored successfully.');
            $this->confirmingUserRestore = false;
            $this->userBeingRestored = null;
        }
    }

    public function render()
    {
        $query = User::with('branch');

        if ($this->showTrashed) {
            $query->withTrashed();
        }

        if ($this->name) {
            $query->where('name', 'like', '%' . $this->name . '%');
        }

        if ($this->role) {
            $query->whereHas('roles', function ($q) {
                $q->where('name', $this->role);
            });
        }

        if ($this->branchId) {
            $query->where('branch_id', $this->branchId);
        }

        $users = $query->paginate(10);

        return view('livewire.users.index', [
            'users' => $users,
            'branches' => $this->branches
        ]);
    }
}
