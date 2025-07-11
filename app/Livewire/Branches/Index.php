<?php

namespace App\Livewire\Branches;

use App\Application\UseCases\ListBranches;
use App\Application\UseCases\DeleteBranch;
use Livewire\Component;
use Illuminate\Support\Facades\Gate;
use Illuminate\Database\Eloquent\Collection;

class Index extends Component
{
    public Collection $branches;

    private ListBranches $listBranchesUseCase;
    private DeleteBranch $deleteBranchUseCase;

    public function boot(ListBranches $listBranchesUseCase, DeleteBranch $deleteBranchUseCase)
    {
        $this->listBranchesUseCase = $listBranchesUseCase;
        $this->deleteBranchUseCase = $deleteBranchUseCase;
    }

    public function mount()
    {
        Gate::authorize('view-branches');
        $this->loadBranches();
    }

    public function loadBranches()
    {
        $this->branches = $this->listBranchesUseCase->execute();
    }

    public function deleteBranch(string $branchId)
    {
        try {
            $this->deleteBranchUseCase->execute($branchId);
            session()->flash('message', 'Branch deleted successfully.');
            $this->loadBranches();
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to delete branch: ' . $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.branches.index', [
            'branches' => $this->branches->toArray(),
        ]);
    }
} 