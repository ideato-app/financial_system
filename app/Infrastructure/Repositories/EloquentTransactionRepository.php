<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Interfaces\TransactionRepository;
use App\Models\Domain\Entities\Transaction as EloquentTransaction;
use App\Models\Domain\Entities\Transaction;
use App\Models\Domain\Entities\CashTransaction;

class EloquentTransactionRepository implements TransactionRepository
{
    public function create(array $attributes): Transaction
    {
        // Filter attributes to only those fillable in the model
        $fillable = (new EloquentTransaction())->getFillable();
        $filtered = array_intersect_key($attributes, array_flip($fillable));
        return EloquentTransaction::create($filtered);
    }

    public function findById(string $id): ?Transaction
    {
        return EloquentTransaction::find($id);
    }

    public function update(string $id, array $attributes): Transaction
    {
        $transaction = EloquentTransaction::findOrFail($id);
        $transaction->update($attributes);
        return $transaction;
    }

    public function delete(string $id): void
    {
        EloquentTransaction::destroy($id);
    }

    public function all(): array
    {
        return EloquentTransaction::with(['agent', 'agent.branch'])->get()->map(function ($transaction) {
            $transactionArray = $transaction->toArray();
            $transactionArray['agent_name'] = $transaction->agent ? $transaction->agent->name : 'N/A';

            // Use branch_id from transaction if available, otherwise fall back to agent's branch
            if ($transaction->branch_id) {
                $branch = \App\Models\Domain\Entities\Branch::find($transaction->branch_id);
                $transactionArray['branch_name'] = $branch ? $branch->name : 'N/A';
                $transactionArray['branch_id'] = $transaction->branch_id;
            } else {
                $transactionArray['branch_name'] = $transaction->agent && $transaction->agent->branch ? $transaction->agent->branch->name : 'N/A';
                $transactionArray['branch_id'] = $transaction->agent && $transaction->agent->branch ? $transaction->agent->branch->id : null;
            }

            return $transactionArray;
        })->toArray();
    }

    public function findByStatus(string $status, ?int $branchId = null): array
    {
        $query = EloquentTransaction::where('status', $status)->with(['agent', 'agent.branch']);

        if ($branchId) {
            $query->where(function ($q) use ($branchId) {
                // Include transactions where the agent belongs to the specific branch
                $q->whereHas('agent', function ($agentQuery) use ($branchId) {
                    $agentQuery->where('branch_id', $branchId);
                });
                // OR include transactions that have the branch_id set directly to the specific branch
                $q->orWhere('branch_id', $branchId);
            });
        }

        return $query->get()->map(function ($transaction) {
            $transactionArray = $transaction->toArray();
            $transactionArray['agent_name'] = $transaction->agent ? $transaction->agent->name : 'N/A';

            // Use branch_id from transaction if available, otherwise fall back to agent's branch
            if ($transaction->branch_id) {
                $branch = \App\Models\Domain\Entities\Branch::find($transaction->branch_id);
                $transactionArray['branch_name'] = $branch ? $branch->name : 'N/A';
                $transactionArray['branch_id'] = $transaction->branch_id;
            } else {
                $transactionArray['branch_name'] = $transaction->agent && $transaction->agent->branch ? $transaction->agent->branch->name : 'N/A';
                $transactionArray['branch_id'] = $transaction->agent && $transaction->agent->branch ? $transaction->agent->branch->id : null;
            }

            return $transactionArray;
        })->toArray();
    }

    public function filter(array $filters = []): array
    {
        $perPage = (int)($filters['perPage'] ?? 1000);
        $page    = max(1, (int)($filters['page'] ?? 1));
        $limit   = $perPage * $page;

        // Ordinary transactions
        $query = EloquentTransaction::query();
        // Flag to skip cash transactions if filtering by transfer line
        $skipCashTransactions = false;

        // Date filters re-enabled
        if (isset($filters['start_date']) && $filters['start_date']) {
            $query->whereDate('created_at', '>=', $filters['start_date']);
        }
        if (isset($filters['end_date']) && $filters['end_date']) {
            $query->whereDate('created_at', '<=', $filters['end_date']);
        }
        if (isset($filters['agent_id'])) {
            $query->where('agent_id', $filters['agent_id']);
        }
        if (isset($filters['branch_id'])) {
            $query->where(function ($q) use ($filters) {
                // Include transactions where the agent belongs to the branch
                $q->whereHas('agent', function ($agentQuery) use ($filters) {
                    $agentQuery->where('branch_id', $filters['branch_id']);
                });
                // OR include transactions that have the branch_id set directly
                $q->orWhere('branch_id', $filters['branch_id']);
            });
        }
        if (isset($filters['customer_name'])) {
            $query->where('customer_name', 'like', '%' . $filters['customer_name'] . '%');
        }
        if (isset($filters['transaction_type'])) {
            $query->where('transaction_type', $filters['transaction_type']);
        }
        // Regular transactions - customer code search
        if (isset($filters['customer_code']) && $filters['customer_code']) {
            // Standardize the customer code format - remove spaces and make case-insensitive
            $customerCode = trim($filters['customer_code']);
            $query->where(function ($q) use ($customerCode) {
                $q->whereRaw('LOWER(customer_code) LIKE ?', ['%' . strtolower($customerCode) . '%']);
            });
        }
        if (isset($filters['receiver_mobile_number']) && $filters['receiver_mobile_number']) {
            // Clean the mobile number to ensure it only contains digits
            $mobileNumber = preg_replace('/[^0-9]/', '', $filters['receiver_mobile_number']);
            $query->where(function ($q) use ($mobileNumber) {
                $q->whereRaw("REPLACE(REPLACE(REPLACE(receiver_mobile_number, '-', ''), ' ', ''), '+', '') LIKE ?", ['%' . $mobileNumber . '%'])
                    ->orWhereRaw("REPLACE(REPLACE(REPLACE(customer_mobile_number, '-', ''), ' ', ''), '+', '') LIKE ?", ['%' . $mobileNumber . '%']);
            });
        }

        // When filtering by transfer_line, we should exclude cash transactions since they don't have line_id
        if (isset($filters['transfer_line']) && $filters['transfer_line'] && !empty($filters['transfer_line'])) {
            // Set flag to skip cash transactions
            $skipCashTransactions = true;
            // For regular transactions, filter by line_id OR handle line_transfer which uses from_line_id/to_line_id
            $transferLineId = $filters['transfer_line'];
            $query->where(function ($q) use ($transferLineId) {
                $q->where('line_id', $transferLineId)
                    ->orWhere('from_line_id', $transferLineId)
                    ->orWhere('to_line_id', $transferLineId);
            });
        }

        // Handle line search (when typing but no specific line selected)
        if (isset($filters['line_search']) && $filters['line_search'] && !empty($filters['line_search'])) {
            // Set flag to skip cash transactions
            $skipCashTransactions = true;
            $lineSearch = $filters['line_search'];

            // Find all lines that match the search pattern
            $matchingLineIds = \App\Models\Domain\Entities\Line::where('mobile_number', 'like', $lineSearch . '%')
                ->pluck('id')
                ->toArray();

            if (!empty($matchingLineIds)) {
                $query->where(function ($q) use ($matchingLineIds) {
                    $q->whereIn('line_id', $matchingLineIds)
                        ->orWhereIn('from_line_id', $matchingLineIds)
                        ->orWhereIn('to_line_id', $matchingLineIds);
                });
            } else {
                // No matching lines found, return empty result set
                $query->whereRaw('1 = 0');
            }
        }

        // Handle amount range filtering
        if (isset($filters['amount_from']) && $filters['amount_from'] !== '') {
            $query->where('amount', '>=', $filters['amount_from']);
        }
        if (isset($filters['amount_to']) && $filters['amount_to'] !== '') {
            $query->where('amount', '<=', $filters['amount_to']);
        }
        if (isset($filters['commission']) && $filters['commission']) {
            $query->where('commission', $filters['commission']);
        }
        if (isset($filters['employee_ids']) && is_array($filters['employee_ids']) && count($filters['employee_ids']) > 0) {
            $query->whereIn('agent_id', $filters['employee_ids']);
        }
        if (isset($filters['branch_ids']) && is_array($filters['branch_ids']) && count($filters['branch_ids']) > 0) {
            $query->where(function ($q) use ($filters) {
                // Include transactions where the agent belongs to one of the branches
                $q->whereHas('agent', function ($agentQuery) use ($filters) {
                    $agentQuery->whereIn('branch_id', $filters['branch_ids']);
                });
                // OR include transactions that have the branch_id set directly to one of the branches
                $q->orWhereIn('branch_id', $filters['branch_ids']);
            });
        }
        // Add reference_number filter for regular transactions
        if (isset($filters['reference_number']) && $filters['reference_number']) {
            // Standardize the reference number format - remove spaces and make case-insensitive
            $refNumber = trim($filters['reference_number']);
            $query->where(function ($q) use ($refNumber) {
                $q->whereRaw('LOWER(reference_number) LIKE ?', ['%' . strtolower($refNumber) . '%']);
            });
        }
        $ordinaryFilterCount = (clone $query)->count();
        $ordinaryTxs = $query->with(['agent', 'agent.branch', 'line', 'line.branch'])
            ->orderBy('transaction_date_time', 'desc')
            ->limit($limit)
            ->get()->map(function ($transaction) {
            $transactionArray = $transaction->toArray();
            $transactionArray['agent_name'] = $transaction->agent ? $transaction->agent->name : 'N/A';

            // Use branch_id from transaction if available, otherwise fall back to agent's branch
            if ($transaction->branch_id) {
                $branch = \App\Models\Domain\Entities\Branch::find($transaction->branch_id);
                $transactionArray['branch_name'] = $branch ? $branch->name : 'N/A';
                $transactionArray['branch_id'] = $transaction->branch_id;
            } else {
                $transactionArray['branch_name'] = $transaction->agent && $transaction->agent->branch ? $transaction->agent->branch->name : 'N/A';
                $transactionArray['branch_id'] = $transaction->agent && $transaction->agent->branch ? $transaction->agent->branch->id : null;
            }

            $transactionArray['commission'] = ($transaction->commission ?? 0) - ($transaction->deduction ?? 0); // Net commission after discount

            // Add 1 EGP fee as negative profit for send transactions
            if ($transaction->transaction_type === 'Transfer') {
                $transactionArray['commission'] -= 1; // Deduct 1 EGP fee from net profit
            }

            $transactionArray['deduction'] = $transaction->deduction ?? 0;
            $transactionArray['source_table'] = 'transactions';
            return $transactionArray;
        });

        // Cash transactions - skip if filtering by transfer line
        $cashTxs = collect();
        $cashFilterCount = 0;
        if (!$skipCashTransactions) {
            $cash = CashTransaction::query();
            // Date filters re-enabled
            if (isset($filters['start_date']) && $filters['start_date']) {
                $cash->whereDate('created_at', '>=', $filters['start_date']);
            }
            if (isset($filters['end_date']) && $filters['end_date']) {
                $cash->whereDate('created_at', '<=', $filters['end_date']);
            }
            if (isset($filters['customer_name'])) {
                $cash->where('customer_name', 'like', '%' . $filters['customer_name'] . '%');
            }
            if (isset($filters['transaction_type'])) {
                $cash->where('transaction_type', $filters['transaction_type']);
            }
            // Cash transactions - customer code search
            if (isset($filters['customer_code']) && $filters['customer_code']) {
                // Standardize the customer code format - remove spaces and make case-insensitive
                $customerCode = trim($filters['customer_code']);
                $cash->where(function ($q) use ($customerCode) {
                    $q->whereRaw('LOWER(customer_code) LIKE ?', ['%' . strtolower($customerCode) . '%']);
                });
            }
            // Handle amount range filtering for cash transactions
            if (isset($filters['amount_from']) && $filters['amount_from'] !== '') {
                $cash->where('amount', '>=', $filters['amount_from']);
            }
            if (isset($filters['amount_to']) && $filters['amount_to'] !== '') {
                $cash->where('amount', '<=', $filters['amount_to']);
            }
            if (isset($filters['agent_id'])) {
                $cash->where('agent_id', $filters['agent_id']);
            }
            if (isset($filters['employee_ids']) && is_array($filters['employee_ids']) && count($filters['employee_ids']) > 0) {
                $cash->whereIn('agent_id', $filters['employee_ids']);
            }
            if (isset($filters['branch_id'])) {
                $cash->whereHas('safe', function ($q) use ($filters) {
                    $q->where('branch_id', $filters['branch_id']);
                });
            }
            // Add filter for receiver_mobile_number (using depositor_mobile_number) for cash transactions
            if (isset($filters['receiver_mobile_number']) && $filters['receiver_mobile_number']) {
                $mobileNumber = preg_replace('/[^0-9]/', '', $filters['receiver_mobile_number']);
                $cash->where(function ($q) use ($mobileNumber) {
                    $q->whereRaw("REPLACE(REPLACE(REPLACE(depositor_mobile_number, '-', ''), ' ', ''), '+', '') LIKE ?", ['%' . $mobileNumber . '%']);
                });
            }
            // Add reference_number filter for cash transactions
            if (isset($filters['reference_number']) && $filters['reference_number']) {
                // Standardize the reference number format - remove spaces and make case-insensitive
                $refNumber = trim($filters['reference_number']);
                $cash->where(function ($q) use ($refNumber) {
                    $q->whereRaw('LOWER(reference_number) LIKE ?', ['%' . strtolower($refNumber) . '%']);
                });
            }
            $cashFilterCount = (clone $cash)->count();
            $cashTxs = $cash->with(['agent', 'agent.branch', 'safe', 'safe.branch'])
                ->orderBy('transaction_date_time', 'desc')
                ->limit($limit)
                ->get()->map(function ($transaction) {
                // Handle expense withdrawals - they should be deducted from branch-specific profit
                $commission = 0; // Cash transactions have no commission
                $deduction = 0;
                $profitContribution = 0; // For profit calculations

                // If this is an expense withdrawal, treat it as a negative profit (expense) for the specific branch
                if (
                    $transaction->transaction_type === 'Withdrawal' &&
                    str_contains($transaction->customer_name, 'Expense:')
                ) {
                    // Expense withdrawals are treated as negative profit for the specific branch
                    $profitContribution = -$transaction->amount; // Negative profit contribution = expense
                }

                return [
                    'id' => $transaction->id,
                    'customer_name' => $transaction->customer_name,
                    'customer_mobile_number' => null,
                    'receiver_mobile_number' => $transaction->depositor_mobile_number ?? null,
                    'customer_code' => $transaction->customer_code ?? null,
                    'amount' => $transaction->amount,
                    'commission' => $commission, // Always 0 for cash transactions
                    'deduction' => $deduction,
                    'profit_contribution' => $profitContribution, // For profit calculations
                    'discount_notes' => null,
                    'notes' => $transaction->notes,
                    'transaction_type' => $transaction->transaction_type,
                    'agent_id' => $transaction->agent_id,
                    'agent_name' => $transaction->agent ? $transaction->agent->name : 'N/A',
                    'branch_name' => $transaction->safe && $transaction->safe->branch ? $transaction->safe->branch->name : 'N/A',
                    'branch_id' => $transaction->safe && $transaction->safe->branch ? $transaction->safe->branch->id : null,
                    'status' => $transaction->status,
                    'transaction_date_time' => $transaction->transaction_date_time,
                    'line_id' => null,
                    'safe_id' => $transaction->safe_id,
                    'is_absolute_withdrawal' => null,
                    'payment_method' => null,
                    'reference_number' => $transaction->reference_number, // Use actual reference_number
                    'created_at' => $transaction->created_at,
                    'updated_at' => $transaction->updated_at,
                    'source_table' => 'cash_transactions',
                ];
            });
        }

        // Merge and sort
        $all = collect(array_merge($ordinaryTxs->toArray(), $cashTxs->toArray()));
        $sortField = $filters['sortField'] ?? 'transaction_date_time';
        $sortDirection = $filters['sortDirection'] ?? 'desc';
        $all = $all->sortBy(function ($tx) use ($sortField) {
            return $tx[$sortField] ?? $tx['transaction_date_time'] ?? $tx['created_at'] ?? null;
        }, SORT_REGULAR, $sortDirection === 'desc');

        $totalTransferred = $all->sum('amount');
        // Exclude line transfer commissions from total commissions (they are expenses, not revenue)
        if (isset($filters['exclude_line_transfer_commission']) && $filters['exclude_line_transfer_commission']) {
            $totalCommission = $all->filter(function ($tx) {
                $transactionType = strtolower(trim($tx['transaction_type'] ?? ''));
                return $transactionType !== 'line_transfer';
            })->sum('commission');
        } else if (isset($filters['transaction_type']) && $filters['transaction_type'] === 'line_transfer') {
            // If filtering only line_transfer, show their actual commission (for detail view)
            $totalCommission = $all->sum('commission');
        } else {
            // Default: legacy behavior (exclude line_transfer commissions)
            $totalCommission = $all->filter(function ($tx) {
                $transactionType = strtolower(trim($tx['transaction_type'] ?? ''));
                return $transactionType !== 'line_transfer';
            })->sum('commission');
        }
        $totalDeductions = $all->sum('deduction');

        // Calculate profit per branch and then sum them up
        $branchProfits = [];
        $totalProfitContribution = 0;

        // Group transactions by branch
        $transactionsByBranch = $all->groupBy('branch_id');

        foreach ($transactionsByBranch as $branchId => $branchTransactions) {
            $branchCommission = $branchTransactions->sum('commission');
            $branchExpenses = $branchTransactions->where('profit_contribution', '<', 0)->sum('profit_contribution');
            $branchProfit = $branchCommission + $branchExpenses; // Commission + negative expenses

            $branchProfits[$branchId] = $branchProfit;
            $totalProfitContribution += $branchExpenses; // Only add the negative expenses
        }

        // Overall net profit calculation
        $netProfit = $totalCommission + $totalProfitContribution;

        // Special handling for line_transfer filter: show fees as negative profit (expenses)
        if (isset($filters['transaction_type']) && $filters['transaction_type'] === 'line_transfer') {
            $lineTransferFees = $all->sum('commission'); // Get the actual line transfer fees
            $netProfit = -$lineTransferFees; // Show as negative (expense)
        }

        $allSorted = $all->values();
        $filterTotalCount = $ordinaryFilterCount + $cashFilterCount;
        $paginated = $allSorted->slice(($page - 1) * $perPage, $perPage)->values();

        return [
            'transactions' => $paginated->all(),
            'total_count'  => $filterTotalCount,
            'totals' => [
                'total_transferred' => $totalTransferred,
                'total_commission' => $totalCommission,
                'total_deductions' => $totalDeductions,
                'net_profit' => $netProfit,
                'branch_profits' => $branchProfits,
            ],
        ];
    }

    public function save(Transaction $transaction): Transaction
    {
        $transaction->save();
        return $transaction;
    }

    public function getTransactionsByLineAndDateRange(string $lineId, \Carbon\Carbon $startDate, \Carbon\Carbon $endDate)
    {
        return EloquentTransaction::where('line_id', $lineId)
            ->whereBetween('transaction_date_time', [$startDate, $endDate])
            ->get();
    }

    public function getTotalReceivedForLine(string $lineId, \Carbon\Carbon $startDate, \Carbon\Carbon $endDate): float
    {
        return EloquentTransaction::where('line_id', $lineId)
            ->whereIn('transaction_type', ['Deposit', 'Receive'])
            ->whereBetween('transaction_date_time', [$startDate, $endDate])
            ->sum('amount');
    }

    public function getTotalSentForLine(string $lineId, \Carbon\Carbon $startDate, \Carbon\Carbon $endDate): float
    {
        return EloquentTransaction::where('line_id', $lineId)
            ->whereIn('transaction_type', ['Transfer', 'Withdrawal'])
            ->whereBetween('transaction_date_time', [$startDate, $endDate])
            ->sum('amount');
    }

    /**
     * Fetch all transactions (ordinary + cash) as a unified list for UI and logic.
     */
    public function allUnified(array $filters = []): array
    {
        $perPage = (int)($filters['perPage'] ?? 30);
        $page    = max(1, (int)($filters['page'] ?? 1));
        $limit   = $perPage * $page; // over-fetch up to current page boundary

        // Flag to skip cash transactions if filtering by transfer line
        $skipCashTransactions = false;

        // Fetch ordinary transactions
        $ordinary = EloquentTransaction::query();
        // Apply filters as in filter() method (copy relevant filter logic)
        if (isset($filters['start_date']) && $filters['start_date']) {
            $ordinary->whereDate('created_at', '>=', $filters['start_date']);
        }
        if (isset($filters['end_date']) && $filters['end_date']) {
            $ordinary->whereDate('created_at', '<=', $filters['end_date']);
        }
        if (isset($filters['agent_id'])) {
            $ordinary->where('agent_id', $filters['agent_id']);
        }
        if (isset($filters['branch_id'])) {
            $ordinary->where(function ($q) use ($filters) {
                // Include transactions where the agent belongs to the specific branch
                $q->whereHas('agent', function ($agentQuery) use ($filters) {
                    $agentQuery->where('branch_id', $filters['branch_id']);
                });
                // OR include transactions that have the branch_id set directly to the specific branch
                $q->orWhere('branch_id', $filters['branch_id']);
            });
        }
        if (isset($filters['customer_name'])) {
            $ordinary->where('customer_name', 'like', '%' . $filters['customer_name'] . '%');
        }
        if (isset($filters['transaction_type'])) {
            $ordinary->where('transaction_type', $filters['transaction_type']);
        }
        // allUnified method - regular transactions customer code search
        if (isset($filters['customer_code']) && $filters['customer_code']) {
            // Standardize the customer code format - remove spaces and make case-insensitive
            $customerCode = trim($filters['customer_code']);
            $ordinary->where(function ($q) use ($customerCode) {
                $q->whereRaw('LOWER(customer_code) LIKE ?', ['%' . strtolower($customerCode) . '%']);
            });
        }
        if (isset($filters['receiver_mobile_number']) && $filters['receiver_mobile_number']) {
            // Clean the mobile number to ensure it only contains digits
            $mobileNumber = preg_replace('/[^0-9]/', '', $filters['receiver_mobile_number']);
            $ordinary->where(function ($q) use ($mobileNumber) {
                $q->whereRaw("REPLACE(REPLACE(REPLACE(receiver_mobile_number, '-', ''), ' ', ''), '+', '') LIKE ?", ['%' . $mobileNumber . '%'])
                    ->orWhereRaw("REPLACE(REPLACE(REPLACE(customer_mobile_number, '-', ''), ' ', ''), '+', '') LIKE ?", ['%' . $mobileNumber . '%']);
            });
        }

        // When filtering by transfer_line, exclude cash transactions since they don't have line_id
        if (isset($filters['transfer_line']) && $filters['transfer_line'] && !empty($filters['transfer_line'])) {
            // Set flag to skip cash transactions
            $skipCashTransactions = true;
            // For regular transactions, filter by line_id OR include line transfers where from_line_id or to_line_id matches
            $transferLineId = $filters['transfer_line'];
            $ordinary->where(function ($q) use ($transferLineId) {
                $q->where('line_id', $transferLineId)
                    ->orWhere('from_line_id', $transferLineId)
                    ->orWhere('to_line_id', $transferLineId);
            });
        }

        // Handle line search (when typing but no specific line selected)
        if (isset($filters['line_search']) && $filters['line_search'] && !empty($filters['line_search'])) {
            // Set flag to skip cash transactions
            $skipCashTransactions = true;
            $lineSearch = $filters['line_search'];

            // Find all lines that match the search pattern
            $matchingLineIds = \App\Models\Domain\Entities\Line::where('mobile_number', 'like', $lineSearch . '%')
                ->pluck('id')
                ->toArray();

            if (!empty($matchingLineIds)) {
                $ordinary->where(function ($q) use ($matchingLineIds) {
                    $q->whereIn('line_id', $matchingLineIds)
                        ->orWhereIn('from_line_id', $matchingLineIds)
                        ->orWhereIn('to_line_id', $matchingLineIds);
                });
            } else {
                // No matching lines found, return empty result set
                $ordinary->whereRaw('1 = 0');
            }
        }

        // Handle amount range filtering
        if (isset($filters['amount_from']) && $filters['amount_from'] !== '') {
            $ordinary->where('amount', '>=', $filters['amount_from']);
        }
        if (isset($filters['amount_to']) && $filters['amount_to'] !== '') {
            $ordinary->where('amount', '<=', $filters['amount_to']);
        }
        if (isset($filters['commission']) && $filters['commission']) {
            $ordinary->where('commission', $filters['commission']);
        }
        if (isset($filters['employee_ids']) && is_array($filters['employee_ids']) && count($filters['employee_ids']) > 0) {
            $ordinary->whereIn('agent_id', $filters['employee_ids']);
        }
        if (isset($filters['branch_ids']) && is_array($filters['branch_ids']) && count($filters['branch_ids']) > 0) {
            $ordinary->where(function ($q) use ($filters) {
                // Include transactions where the agent belongs to one of the branches
                $q->whereHas('agent', function ($agentQuery) use ($filters) {
                    $agentQuery->whereIn('branch_id', $filters['branch_ids']);
                });
                // OR include transactions that have the branch_id set directly to one of the branches
                $q->orWhereIn('branch_id', $filters['branch_ids']);
            });
        }
        if (isset($filters['reference_number']) && $filters['reference_number']) {
            // Standardize the reference number format - remove spaces and make case-insensitive
            $refNumber = trim($filters['reference_number']);
            $ordinary->where(function ($q) use ($refNumber) {
                $q->whereRaw('LOWER(reference_number) LIKE ?', ['%' . strtolower($refNumber) . '%']);
            });
        }
        $ordinaryCount = (clone $ordinary)->count();

        $ordinaryTxs = $ordinary->with(['agent', 'agent.branch', 'line', 'line.branch'])
            ->orderBy('transaction_date_time', 'desc')
            ->limit($limit)
            ->get()->map(function ($transaction) {
            $arr = $transaction->toArray();
            $arr['agent_name'] = $transaction->agent ? $transaction->agent->name : 'N/A';

            // Use branch_id from transaction if available, otherwise fall back to agent's branch
            if ($transaction->branch_id) {
                $branch = \App\Models\Domain\Entities\Branch::find($transaction->branch_id);
                $arr['branch_name'] = $branch ? $branch->name : 'N/A';
                $arr['branch_id'] = $transaction->branch_id;
            } else {
                $arr['branch_name'] = $transaction->agent && $transaction->agent->branch ? $transaction->agent->branch->name : 'N/A';
                $arr['branch_id'] = $transaction->agent && $transaction->agent->branch ? $transaction->agent->branch->id : null;
            }

            $arr['commission'] = ($transaction->commission ?? 0) - ($transaction->deduction ?? 0); // Net commission after discount

            // Add 1 EGP fee as negative profit for send transactions
            if ($transaction->transaction_type === 'Transfer') {
                $arr['commission'] -= 1; // Deduct 1 EGP fee from net profit
            }

            // Treat all commissions as positive profit for now
            $arr['profit_contribution'] = $arr['commission'];

            $arr['deduction'] = $transaction->deduction ?? 0;
            $arr['source_table'] = 'transactions';
            $arr['descriptive_transaction_name'] = $transaction->descriptive_transaction_name;
            return $arr;
        });

        // Fetch cash transactions - skip if filtering by transfer line
        $cashTxs   = collect();
        $cashCount = 0;
        if (!$skipCashTransactions) {
            $cash = CashTransaction::query();
            if (isset($filters['start_date']) && $filters['start_date']) {
                $cash->whereDate('created_at', '>=', $filters['start_date']);
            }
            if (isset($filters['end_date']) && $filters['end_date']) {
                $cash->whereDate('created_at', '<=', $filters['end_date']);
            }
            if (isset($filters['customer_name'])) {
                $cash->where('customer_name', 'like', '%' . $filters['customer_name'] . '%');
            }
            if (isset($filters['transaction_type'])) {
                $cash->where('transaction_type', $filters['transaction_type']);
            }
            // allUnified method - cash transactions customer code search
            if (isset($filters['customer_code']) && $filters['customer_code']) {
                // Standardize the customer code format - remove spaces and make case-insensitive
                $customerCode = trim($filters['customer_code']);
                $cash->where(function ($q) use ($customerCode) {
                    $q->whereRaw('LOWER(customer_code) LIKE ?', ['%' . strtolower($customerCode) . '%']);
                });
            }
            // Handle amount range filtering for cash transactions
            if (isset($filters['amount_from']) && $filters['amount_from'] !== '') {
                $cash->where('amount', '>=', $filters['amount_from']);
            }
            if (isset($filters['amount_to']) && $filters['amount_to'] !== '') {
                $cash->where('amount', '<=', $filters['amount_to']);
            }
            // Add agent_id and employee_ids filter for cash transactions
            if (isset($filters['agent_id'])) {
                $cash->where('agent_id', $filters['agent_id']);
            }
            if (isset($filters['employee_ids']) && is_array($filters['employee_ids']) && count($filters['employee_ids']) > 0) {
                $cash->whereIn('agent_id', $filters['employee_ids']);
            }
            // Add branch filtering for cash transactions
            if (isset($filters['branch_id'])) {
                $cash->where(function ($q) use ($filters) {
                    // Include transactions where the agent belongs to the specific branch
                    $q->whereHas('agent', function ($agentQuery) use ($filters) {
                        $agentQuery->where('branch_id', $filters['branch_id']);
                    });
                    // OR include expense withdrawals that have the destination_branch_id set to the specific branch
                    $q->orWhere(function ($expenseQuery) use ($filters) {
                        $expenseQuery->where('transaction_type', 'Withdrawal')
                            ->where('customer_name', 'like', 'Expense:%')
                            ->where('destination_branch_id', $filters['branch_id']);
                    });
                    // OR include transactions where the safe belongs to the specific branch
                    $q->orWhereHas('safe', function ($safeQuery) use ($filters) {
                        $safeQuery->where('branch_id', $filters['branch_id']);
                    });
                });
            }
            if (isset($filters['branch_ids']) && is_array($filters['branch_ids']) && count($filters['branch_ids']) > 0) {
                $cash->where(function ($q) use ($filters) {
                    // Include transactions where the agent belongs to one of the branches
                    $q->whereHas('agent', function ($agentQuery) use ($filters) {
                        $agentQuery->whereIn('branch_id', $filters['branch_ids']);
                    });
                    // OR include expense withdrawals that have destination_branch_id in the filtered branches
                    $q->orWhere(function ($expenseQuery) use ($filters) {
                        $expenseQuery->where('transaction_type', 'Withdrawal')
                            ->where('customer_name', 'like', 'Expense:%')
                            ->whereIn('destination_branch_id', $filters['branch_ids']);
                    });
                    // OR include transactions where the safe belongs to one of the branches
                    $q->orWhereHas('safe', function ($safeQuery) use ($filters) {
                        $safeQuery->whereIn('branch_id', $filters['branch_ids']);
                    });
                });
            }
            if (isset($filters['reference_number']) && $filters['reference_number']) {
                // Standardize the reference number format - remove spaces and make case-insensitive
                $refNumber = trim($filters['reference_number']);
                $cash->where(function ($q) use ($refNumber) {
                    $q->whereRaw('LOWER(reference_number) LIKE ?', ['%' . strtolower($refNumber) . '%']);
                });
            }
            // Add filter for receiver_mobile_number (using depositor_mobile_number)
            if (isset($filters['receiver_mobile_number']) && $filters['receiver_mobile_number']) {
                $mobileNumber = preg_replace('/[^0-9]/', '', $filters['receiver_mobile_number']);
                $cash->where(function ($q) use ($mobileNumber) {
                    $q->whereRaw("REPLACE(REPLACE(REPLACE(depositor_mobile_number, '-', ''), ' ', ''), '+', '') LIKE ?", ['%' . $mobileNumber . '%']);
                });
            }
            $cashCount = (clone $cash)->count();

            $cashTxs = $cash->with(['agent', 'agent.branch', 'safe.branch'])
                ->orderBy('transaction_date_time', 'desc')
                ->limit($limit)
                ->get()->map(function ($transaction) {
                // Handle expense withdrawals - they should be deducted from branch-specific profit
                $commission = 0; // Cash transactions have no commission
                $deduction = 0;
                $profitContribution = 0; // For profit calculations

                // Determine the correct branch for this transaction
                $branchId = null;
                $branchName = 'N/A';

                // For expense withdrawals, use the destination_branch_id or safe's branch
                if (
                    $transaction->transaction_type === 'Withdrawal' &&
                    str_contains($transaction->customer_name, 'Expense:')
                ) {
                    // Expense withdrawals should be attributed to the branch where the expense occurred
                    if ($transaction->destination_branch_id) {
                        $branchId = $transaction->destination_branch_id;
                        // Get branch name from the destination branch
                        $branch = \App\Models\Domain\Entities\Branch::find($branchId);
                        $branchName = $branch ? $branch->name : 'N/A';
                    } elseif ($transaction->safe && $transaction->safe->branch) {
                        $branchId = $transaction->safe->branch->id;
                        $branchName = $transaction->safe->branch->name;
                    }
                    // Expense withdrawals are treated as negative profit for the specific branch
                    $profitContribution = -$transaction->amount; // Negative profit contribution = expense
                } else {
                    // For other cash transactions, use safe's branch
                    if ($transaction->safe && $transaction->safe->branch) {
                        $branchId = $transaction->safe->branch->id;
                        $branchName = $transaction->safe->branch->name;
                    }
                }

                return [
                    'id' => $transaction->id,
                    'customer_name' => $transaction->customer_name,
                    'customer_mobile_number' => null,
                    'receiver_mobile_number' => $transaction->depositor_mobile_number ?? null,
                    'customer_code' => $transaction->customer_code ?? null,
                    'amount' => $transaction->amount,
                    'commission' => $commission, // Always 0 for cash transactions
                    'deduction' => $deduction,
                    'profit_contribution' => $profitContribution, // For profit calculations
                    'discount_notes' => null,
                    'notes' => $transaction->notes,
                    'transaction_type' => $transaction->transaction_type,
                    'descriptive_transaction_name' => $transaction->descriptive_transaction_name,
                    'agent_id' => $transaction->agent_id,
                    'agent_name' => $transaction->agent ? $transaction->agent->name : 'N/A',
                    'branch_name' => $branchName,
                    'branch_id' => $branchId,
                    'status' => $transaction->status,
                    'transaction_date_time' => $transaction->transaction_date_time,
                    'line_id' => null,
                    'safe_id' => $transaction->safe_id,
                    'is_absolute_withdrawal' => null,
                    'payment_method' => null,
                    'reference_number' => $transaction->reference_number, // Use actual reference_number
                    'created_at' => $transaction->created_at,
                    'updated_at' => $transaction->updated_at,
                    'source_table' => 'cash_transactions',
                ];
            });
        }

        $ordinaryTxsArr = $ordinaryTxs->toArray();
        $cashTxsArr = $cashTxs->toArray();

        $all = collect(array_merge($ordinaryTxsArr, $cashTxsArr));

        // Apply sorting if provided
        if (isset($filters['sortField']) && isset($filters['sortDirection'])) {
            $sortField = $filters['sortField'];
            $sortDirection = $filters['sortDirection'];

            // Map frontend field names to actual database field names
            $fieldMapping = [
                'customer_name' => 'customer_name',
                'customer_mobile_number' => 'customer_mobile_number',
                'amount' => 'amount',
                'commission' => 'commission',
                'transaction_type' => 'transaction_type',
                'agent_name' => 'agent_name',
                'created_at' => 'created_at',
                'reference_number' => 'reference_number',
            ];

            $actualField = $fieldMapping[$sortField] ?? 'created_at';

            if ($sortDirection === 'asc') {
                $all = $all->sortBy($actualField);
            } else {
                $all = $all->sortByDesc($actualField);
            }
        } else {
            // Default sorting by transaction date time descending
            $all = $all->sortByDesc('transaction_date_time');
        }

        $all = $all->values();
        $totalCount = $ordinaryCount + $cashCount;

        // Slice to the requested page window
        $paginated = $all->slice(($page - 1) * $perPage, $perPage)->values();

        $totalTransferred = $all->sum('amount');
        // Exclude line transfer commissions from total commissions (they are expenses, not revenue)
        $totalCommission = $all->filter(function ($tx) {
            return strtolower($tx['transaction_type']) !== 'line_transfer';
        })->sum('commission');
        $totalDeductions = $all->sum('deduction');

        // Calculate profit per branch and then sum them up
        $branchProfits = [];
        $totalProfitContribution = 0;

        // Group transactions by branch
        $transactionsByBranch = $all->groupBy('branch_id');

        foreach ($transactionsByBranch as $branchId => $branchTransactions) {
            $branchCommission = $branchTransactions->sum('commission');
            $branchExpenses = $branchTransactions->where('profit_contribution', '<', 0)->sum('profit_contribution');
            $branchProfit = $branchCommission + $branchExpenses; // Commission + negative expenses

            $branchProfits[$branchId] = $branchProfit;
            $totalProfitContribution += $branchExpenses; // Only add the negative expenses
        }

        // Overall net profit calculation (second method)
        $netProfit = $totalCommission + $totalProfitContribution;

        // Special handling for line_transfer filter: show fees as negative profit (expenses)
        if (isset($filters['transaction_type']) && $filters['transaction_type'] === 'line_transfer') {
            $lineTransferFees = $all->sum('commission'); // Get the actual line transfer fees
            $netProfit = -$lineTransferFees; // Show as negative (expense)
        }

        return [
            'transactions' => $paginated->toArray(),
            'total_count'  => $totalCount,
            'totals' => [
                'total_transferred' => $totalTransferred,
                'total_commission' => $totalCommission,
                'total_deductions' => $totalDeductions,
                'net_profit' => $netProfit,
                'branch_profits' => $branchProfits,
            ],
        ];
    }

    /**
     * Enhanced query method using Transaction model scopes
     */
    public function getFilteredTransactions(array $filters = []): array
    {
        $query = EloquentTransaction::query()->with(['agent', 'branch', 'line', 'safe']);

        // Apply scopes
        if (isset($filters['mobile_number'])) {
            $query->filterByMobile($filters['mobile_number']);
        }

        if (isset($filters['reference_number'])) {
            $query->filterByReference($filters['reference_number']);
        }

        if (isset($filters['amount_from']) || isset($filters['amount_to'])) {
            $query->amountBetween($filters['amount_from'] ?? null, $filters['amount_to'] ?? null);
        }

        if (isset($filters['start_date']) || isset($filters['end_date'])) {
            $query->dateBetween($filters['start_date'] ?? null, $filters['end_date'] ?? null);
        }

        if (isset($filters['branch_ids'])) {
            $query->branches($filters['branch_ids']);
        } elseif (isset($filters['branch_id'])) {
            $query->branches($filters['branch_id']);
        }

        if (isset($filters['agent_id'])) {
            $query->agent($filters['agent_id']);
        }

        // Apply sorting
        $sortField = $filters['sort_field'] ?? 'transaction_date_time';
        $sortDirection = $filters['sort_direction'] ?? 'desc';
        $query->orderBy($sortField, $sortDirection);

        return $query->get()->map(function ($transaction) {
            $arr = $transaction->toArray();
            $arr['agent_name'] = $transaction->agent ? $transaction->agent->name : 'N/A';
            $arr['branch_name'] = $transaction->branch ? $transaction->branch->name : ($transaction->agent && $transaction->agent->branch ? $transaction->agent->branch->name : 'N/A');
            return $arr;
        })->toArray();
    }

    /**
     * Get employee transactions with employment date consideration
     */
    public function getEmployeeTransactions(int $employeeId, array $filters = []): array
    {
        $employee = \App\Domain\Entities\User::find($employeeId);
        if (!$employee) {
            return [];
        }

        // Use employment start date if no start date provided
        if (!isset($filters['start_date']) && $employee->employment_start_date) {
            $filters['start_date'] = $employee->employment_start_date;
        }

        $filters['agent_id'] = $employeeId;
        return $this->getFilteredTransactions($filters);
    }

    /**
     * Get customer transactions by customer identifier
     */
    public function getCustomerTransactions(string $customerIdentifier, array $filters = []): array
    {
        // Try to find customer by code, name, or mobile
        $customer = \App\Models\Domain\Entities\Customer::where('customer_code', 'like', "%{$customerIdentifier}%")
            ->orWhere('name', 'like', "%{$customerIdentifier}%")
            ->orWhere('mobile_number', 'like', "%{$customerIdentifier}%")
            ->first();

        if ($customer) {
            // Filter by customer code for exact matches
            $filters['customer_code'] = $customer->customer_code;
        } else {
            // Try mobile number search in transactions
            $filters['mobile_number'] = $customerIdentifier;
        }

        return $this->getFilteredTransactions($filters);
    }

    public function countAllPending(): int
    {
        $ordinaryCount = EloquentTransaction::where('status', 'Pending')->count();
        $cashCount = CashTransaction::where('status', 'pending')->count();
        return $ordinaryCount + $cashCount;
    }
}
