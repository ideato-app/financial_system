<?php

namespace App\Application\UseCases;

use App\Domain\Interfaces\TransactionRepository;
use Illuminate\Support\Facades\Auth;
use App\Constants\Roles;
use Illuminate\Support\Facades\Gate;

class ListTransactions
{
    private TransactionRepository $transactionRepository;

    public function __construct(TransactionRepository $transactionRepository)
    {
        $this->transactionRepository = $transactionRepository;
    }

    public function execute(array $filters = []): array
    {
        $user = Auth::user();

        if (!$user) {
            return ['transactions' => [], 'total_count' => 0];
        }

        // Admin, General Supervisor, and Auditor can see all transactions across branches
        if (Gate::forUser($user)->allows('view-all-branches-data')) {
            $result = $this->transactionRepository->allUnified($filters);
            return ['transactions' => $result['transactions'] ?? [], 'total_count' => $result['total_count'] ?? 0];
        }

        // Branch Managers and Agents can see all transactions for their assigned branch
        elseif (Gate::forUser($user)->allows('view-own-branch-data') || Gate::forUser($user)->allows('view-agent-dashboard')) {
            if ($user->hasRole(Roles::BRANCH_MANAGER)) {
                $filters['branch_id'] = $user->branch_id;
            } else {
                $filters['agent_id'] = $user->id;
            }
            $result = $this->transactionRepository->allUnified($filters);
            return ['transactions' => $result['transactions'] ?? [], 'total_count' => $result['total_count'] ?? 0];
        }

        // Trainees can see all transactions for their assigned branch
        elseif (Gate::forUser($user)->allows('view-trainee-dashboard')) {
            $filters['branch_id'] = $user->branch_id;
            $result = $this->transactionRepository->allUnified($filters);
            return ['transactions' => $result['transactions'] ?? [], 'total_count' => $result['total_count'] ?? 0];
        }

        // Default for users with no specific view permissions
        return ['transactions' => [], 'total_count' => 0];
    }
}
