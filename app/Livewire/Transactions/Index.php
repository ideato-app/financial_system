<?php

namespace App\Livewire\Transactions;

use App\Application\UseCases\ListTransactions;
use App\Application\UseCases\DeleteTransaction;
use Livewire\Component;
use Illuminate\Support\Facades\Gate;


class Index extends Component
{
    public array $transactions;

    public $sortField = 'transaction_date_time';
    public $sortDirection = 'desc';

    public $customer_code;
    public $receiver_mobile_number;
    public $transfer_line;
    public $amount_from; // Changed from single amount to range
    public $amount_to;   // Added for amount range
    public $commission;
    public $transaction_type;
    public $start_date;
    public $end_date;
    public $employee_ids = [];
    public $branch_ids = [];
    public $reference_number;

    public $perPage = 30; // Default rows per page
    public $currentPage = 1;
    public $lazyLoading = false;

    private ListTransactions $listTransactionsUseCase;
    private DeleteTransaction $deleteTransactionUseCase;

    public function boot(ListTransactions $listTransactionsUseCase, DeleteTransaction $deleteTransactionUseCase)
    {
        $this->listTransactionsUseCase = $listTransactionsUseCase;
        $this->deleteTransactionUseCase = $deleteTransactionUseCase;
    }

    public function mount()
    {
        // Authorization check for viewing transactions
        if (auth()->user()->hasRole('agent') || auth()->user()->hasRole('trainee')) {
            session()->flash('error', 'You are not authorized to access the transactions page.');
            return redirect()->route('dashboard');
        }
        Gate::authorize('view-transactions');

        // Initialize transactions array
        $this->transactions = [];

        $this->loadTransactions();
    }

    public int $totalTransactions = 0;

    public function loadTransactions()
    {
        $filters = array_filter([
            'customer_code' => $this->customer_code,
            'receiver_mobile_number' => $this->receiver_mobile_number,
            'transfer_line' => $this->transfer_line,
            'amount_from' => $this->amount_from, // Changed from amount to amount_from
            'amount_to' => $this->amount_to,     // Added amount_to
            'commission' => $this->commission,
            'transaction_type' => $this->transaction_type,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'employee_ids' => !empty($this->employee_ids) ? $this->employee_ids : null,
            'branch_ids' => !empty($this->branch_ids) ? $this->branch_ids : null,
            'reference_number' => $this->reference_number,
            'sortField' => $this->sortField,
            'sortDirection' => $this->sortDirection,
        ], function ($value) {
            return $value !== null && $value !== '';
        });

        $filters['perPage'] = $this->perPage;
        $filters['page']    = $this->lazyLoading ? 1 : $this->currentPage;
        if ($this->lazyLoading) {
            // For lazy loading accumulate pages by increasing perPage
            $filters['perPage'] = $this->perPage * $this->currentPage;
            $filters['page']    = 1;
        }

        $result = $this->listTransactionsUseCase->execute($filters);
        $total  = $result['total_count'] ?? 0;

        $this->transactions     = $result['transactions'] ?? [];
        $this->totalTransactions = $total;

        if (!$this->lazyLoading) {
            $this->currentPage = max(1, min($this->currentPage, (int) ceil($total / $this->perPage) ?: 1));
        }
    }

    public function updatedPerPage($value)
    {
        $this->currentPage = 1;
        $this->loadTransactions();
    }

    public function goToPage($page)
    {
        $this->currentPage = $page;
        $this->loadTransactions();
    }

    public function loadMore()
    {
        $this->currentPage++;
        $this->lazyLoading = true;
        $this->loadTransactions();
    }

    public function filter()
    {
        $this->currentPage = 1;
        $this->lazyLoading = false;
        $this->loadTransactions();
    }

    // Add updated hook for real-time filtering
    public function updated($propertyName)
    {
        // If any filter property is updated, reset pagination to first page
        if (in_array($propertyName, [
            'customer_code',
            'receiver_mobile_number',
            'transfer_line',
            'amount_from',
            'amount_to',
            'commission',
            'transaction_type',
            'start_date',
            'end_date',
            'employee_ids',
            'branch_ids',
            'reference_number'
        ])) {
            // Don't auto-filter on every keystroke to avoid too many requests
            // User will need to click the filter button
        }
    }

    public function resetFilters()
    {
        $this->customer_code = null;
        $this->receiver_mobile_number = null;
        $this->transfer_line = null;
        $this->amount_from = null;
        $this->amount_to = null;
        $this->commission = null;
        $this->transaction_type = null;
        $this->start_date = null;
        $this->end_date = null;
        $this->employee_ids = [];
        $this->branch_ids = [];
        $this->reference_number = null;
        $this->currentPage = 1;
        $this->lazyLoading = false;
        $this->loadTransactions();
    }

    public function sortBy($field)
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
        $this->loadTransactions();
    }

    public function deleteTransaction(string $referenceNumber)
    {
        // Find the transaction in the list to determine its source
        $transaction = collect($this->transactions)->firstWhere('reference_number', $referenceNumber);
        
        if (!$transaction) {
            session()->flash('error', 'Transaction not found.');
            return;
        }

        // Check if it's a cash transaction
        if (isset($transaction['source_table']) && $transaction['source_table'] === 'cash_transactions') {
            $this->deleteCashTransaction($transaction['id']);
            return;
        }

        // For ordinary transactions
        try {
            $this->deleteTransactionUseCase->execute($transaction['id']);
            session()->flash('message', 'Transaction deleted successfully.');
            $this->loadTransactions();
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to delete transaction: ' . $e->getMessage());
        }
    }

    public function deleteCashTransaction(string $cashTransactionId)
    {
        try {
            $cashTransaction = \App\Models\Domain\Entities\CashTransaction::find($cashTransactionId);
            if ($cashTransaction) {
                $cashTransaction->delete();
                session()->flash('message', 'Cash transaction deleted successfully.');
            } else {
                session()->flash('error', 'Cash transaction not found.');
            }
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to delete cash transaction: ' . $e->getMessage());
        }
        $this->loadTransactions();
    }

    public function render()
    {
        return view('livewire.transactions.index', [
            'transactions' => $this->transactions,
            'perPage' => $this->perPage,
            'currentPage' => $this->currentPage,
            'totalTransactions' => $this->totalTransactions ?? count($this->transactions),
        ]);
    }
}
