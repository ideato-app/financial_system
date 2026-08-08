<?php

namespace App\Livewire\Reports;

use Livewire\Component;
use Livewire\Attributes\Locked;
use App\Models\Domain\Entities\Transaction;
use App\Models\Domain\Entities\CashTransaction;
use App\Domain\Entities\User;
use App\Models\Domain\Entities\Branch;
use App\Models\Domain\Entities\Customer;
use App\Services\TotalsService;
use App\Infrastructure\Repositories\EloquentTransactionRepository;
use App\Exports\AutoSizeTransactionsExport;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use App\Models\Domain\Entities\Safe; // Correct import for Safe model
use App\Models\Domain\Entities\Line;

class Enhanced extends Component
{
    // Universal filter properties
    public $showLoading = false;
    public $referenceNumber = '';
    public $amountFrom = '';
    public $amountTo = '';
    public $startDate = '';
    public $endDate = '';
    public $selectedBranches = [];
    public $selectedEmployee = '';
    public $customerSearch = '';

    // Report type and data
    public $reportType = 'transactions'; // transactions, employee, customer, branch
    public $transactions = [];
    public $totals = [];
    public $customers = [];
    public $employees = [];
    public $branches = [];

    // Table state
    public $sortField = 'transaction_date_time';
    public $sortDirection = 'desc';
    public $perPage = 50;
    public $totalCount = 0;
    public $hasMore = false;

    // Column filters
    public $filterCustomerName = '';
    public $filterTransactionType = '';
    public $filterStatus = '';
    public $filterEmployee = '';
    public $filterBranch = '';
    public $filterMobileNumber = '';

    // Report-specific properties
    public $selectedCustomer = null;
    public $customerDetails = null;
    public $employeeDetails = null;
    public $branchDetails = [];

    // New properties for line filtering
    public $selectedLine = ''; // New property for line filter
    public $lines = []; // Store lines for dropdown
    public $lineSearch = ''; // New property for line search
    public $filteredLines = []; // Store filtered lines for suggestions

    // Services - these should not be serialized
    #[Locked]
    private ?TotalsService $totalsService = null;
    #[Locked]
    private ?EloquentTransactionRepository $transactionRepository = null;

    private function getTotalsService(): TotalsService
    {
        if (!$this->totalsService) {
            $this->totalsService = app(TotalsService::class);
        }
        return $this->totalsService;
    }

    private function getTransactionRepository(): EloquentTransactionRepository
    {
        if (!$this->transactionRepository) {
            $this->transactionRepository = app(EloquentTransactionRepository::class);
        }
        return $this->transactionRepository;
    }

    public function mount()
    {
        // Authorization check
        Gate::authorize('view-all-reports');

        // Set default date range (last 30 days)
        $this->startDate = now()->subDays(30)->format('Y-m-d');
        $this->endDate = now()->format('Y-m-d');

        // Load reference data
        $this->loadReferenceData();

        // Generate initial report
        $this->generateReport();
    }

    public function loadReferenceData()
    {
        $user = Auth::user();

        // Load branches based on user permissions
        if (Gate::forUser($user)->allows('view-all-branches-data')) {
            $this->branches = Branch::where('is_active', true)->get();
        } else {
            $this->branches = Branch::where('id', $user->branch_id)->where('is_active', true)->get();
        }

        // Load all lines for the dropdown
        $this->lines = Line::all()->pluck('mobile_number', 'id')->toArray();

        // Initialize filtered lines (empty at start)
        $this->filteredLines = [];

        // Load employees based on user permissions
        if (Gate::forUser($user)->allows('view-all-branches-data')) {
            $this->employees = User::all();
        } else {
            $this->employees = User::where('branch_id', $user->branch_id)->get();
        }

        // Load customers (id/name/code only to avoid serializing full rows)
        $this->customers = Customer::select('id', 'name', 'customer_code')->get();
    }

    public function updatedReportType()
    {
        $this->resetFilters();
        $this->generateReport();
    }

    public function resetFilters()
    {
        $this->reset([
            'referenceNumber',
            'amountFrom',
            'amountTo',
            'selectedBranches',
            'selectedEmployee',
            'customerSearch',
            'filterCustomerName',
            'filterTransactionType',
            'filterStatus',
            'filterEmployee',
            'filterBranch',
            'selectedLine', // Reset selected line
            'lineSearch', // Reset line search
        ]);

        // Clear filtered lines
        $this->filteredLines = [];
    }

    public function updatedLineSearch()
    {
        if (empty($this->lineSearch)) {
            $this->filteredLines = [];
            $this->selectedLine = '';
            return;
        }

        // Search for lines that start with the input
        $this->filteredLines = Line::where('mobile_number', 'like', $this->lineSearch . '%')
            ->orderBy('mobile_number')
            ->limit(10) // Limit to 10 suggestions
            ->pluck('mobile_number', 'id')
            ->toArray();
    }

    public function selectLine($lineId, $mobileNumber)
    {
        $this->selectedLine = $lineId;
        $this->lineSearch = $mobileNumber;
        $this->filteredLines = [];

        // Trigger report regeneration
        $this->generateReport();
    }

    public function generateReport()
    {
        $filters = $this->buildFilters();

        switch ($this->reportType) {
            case 'employee':
                $this->generateEmployeeReport($filters);
                break;
            case 'customer':
                $this->generateCustomerReport($filters);
                break;
            case 'branch':
                $this->generateBranchReport($filters);
                break;
            default:
                $this->generateTransactionReport($filters);
        }
    }

    private function buildFilters(): array
    {
        $filters = [];

        // Universal filters
        if ($this->startDate) {
            $filters['start_date'] = $this->startDate;
        }
        if ($this->endDate) {
            $filters['end_date'] = $this->endDate;
        }
        if ($this->referenceNumber) {
            $filters['reference_number'] = $this->referenceNumber;
        }
        if ($this->amountFrom !== '' && $this->amountFrom !== null && is_numeric($this->amountFrom)) {
            $filters['amount_from'] = (float)$this->amountFrom;
        }
        if ($this->amountTo !== '' && $this->amountTo !== null && is_numeric($this->amountTo)) {
            $filters['amount_to'] = (float)$this->amountTo;
        }
        if (!empty($this->selectedBranches)) {
            // Check if "all" is selected (show all branches)
            if (in_array('all', $this->selectedBranches)) {
                // Don't apply branch filter when "all" is selected
                // This will show data from all branches
            } else {
                // Filter out empty values and apply specific branch filter
                $validBranches = array_filter($this->selectedBranches, function ($branchId) {
                    return !empty($branchId);
                });
                if (!empty($validBranches)) {
                    $filters['branch_ids'] = $validBranches;
                }
            }
        }
        if ($this->selectedEmployee) {
            $filters['employee_ids'] = [$this->selectedEmployee];
        }
        if ($this->selectedLine) {
            $filters['transfer_line'] = $this->selectedLine;
        } elseif ($this->lineSearch) {
            // If no specific line selected but search text exists, use search term
            $filters['line_search'] = $this->lineSearch;
        }

        // Column filters
        if ($this->filterCustomerName) {
            $filters['customer_name'] = $this->filterCustomerName;
        }
        if ($this->filterTransactionType) {
            // Map filter values to exact DB values 
            $typeMap = [
                'receive' => 'Receive',
                'transfer' => 'Transfer',
                'line_transfer' => 'line_transfer',
                'deposit' => 'Deposit',
                'withdrawal' => 'Withdrawal',
            ];
            $filters['transaction_type'] = $typeMap[$this->filterTransactionType] ?? $this->filterTransactionType;
        }
        if ($this->filterStatus) {
            $filters['status'] = $this->filterStatus;
        }
        if ($this->filterMobileNumber) {
            $filters['receiver_mobile_number'] = $this->filterMobileNumber;
        }

        // Sorting
        $filters['sortField'] = $this->sortField;
        $filters['sortDirection'] = $this->sortDirection;

        return $filters;
    }

    private function generateTransactionReport($filters)
    {
        // Fetch paginated transactions from repository
        $filters['perPage'] = $this->perPage;
        $filters['page']    = 1;
        $result = $this->getTransactionRepository()->allUnified($filters);
        $allTransactions = collect($result['transactions']);

        // Modify line transfer commissions for display
        $paginatedTransactions = $allTransactions->map(function ($transaction) {
            if (strtolower($transaction['transaction_type']) === 'line_transfer') {
                $transaction['commission'] = 0;
            }
            return $transaction;
        })->all();

        $this->transactions = $paginatedTransactions;
        $this->totalCount = $result['total_count'] ?? $allTransactions->count();
        $this->hasMore = $this->totalCount > $this->perPage;

        // Always exclude line transfer commissions from totals unless explicitly filtering for line_transfer
        $totalsFilters = $filters;
        if (empty($this->filterTransactionType)) {
            // Exclude line_transfer from totals by default
            $totalsFilters['exclude_line_transfer_commission'] = true;
        }
        $totalsServiceTotals = $this->getTotalsService()->calculateTotals($totalsFilters);
        $this->totals = [
            'total_turnover' => $totalsServiceTotals['total_turnover'],
            'total_commissions' => $totalsServiceTotals['total_commissions'],
            'total_deductions' => $totalsServiceTotals['total_deductions'],
            'total_expenses' => $totalsServiceTotals['total_expenses'],
            'net_profit' => $totalsServiceTotals['net_profit'],
            'transactions_count' => $totalsServiceTotals['transactions_count'],
        ];
    }

    private function generateEmployeeReport($filters)
    {
        // If no employee selected, show all transactions
        if (!$this->selectedEmployee) {
            $this->employeeDetails = null;
            $this->generateTransactionReport($filters);
            return;
        }

        // Get employee details
        $employee = User::find($this->selectedEmployee);
        if (!$employee) {
            $this->transactions = [];
            $this->totals = [];
            $this->employeeDetails = null;
            return;
        }

        $this->employeeDetails = [
            'name' => $employee->name,
            'id' => $employee->id,
            'phone' => $employee->phone_number ?? 'N/A',
            'branch' => $employee->branch->name ?? 'N/A',
            'employment_start_date' => $employee->employment_start_date,
        ];

        // If no date range specified, use employment start date
        if (!$this->startDate && $employee->employment_start_date) {
            $filters['start_date'] = $employee->employment_start_date;
        }

        // Generate report
        $this->generateTransactionReport($filters);
    }

    private function generateCustomerReport($filters)
    {
        // If no customer search, show all transactions
        if (!$this->customerSearch) {
            $this->customerDetails = null;
            $this->generateTransactionReport($filters);
            return;
        }

        // Find customer by code, name, or mobile
        $customer = Customer::where('customer_code', 'like', "%{$this->customerSearch}%")
            ->orWhere('name', 'like', "%{$this->customerSearch}%")
            ->orWhere('mobile_number', 'like', "%{$this->customerSearch}%")
            ->first();

        if ($customer) {
            $this->customerDetails = [
                'name' => $customer->name,
                'customer_code' => $customer->customer_code,
                'mobile_number' => $customer->mobile_number,
                'balance' => $customer->balance,
                'is_client' => $customer->is_client,
                'safe_balance' => $this->getCustomerSafeBalance($customer),
                'allow_debt' => $customer->allow_debt,
                'max_debt_limit' => $customer->max_debt_limit,
            ];

            // Filter by customer
            $filters['customer_code'] = $customer->customer_code;
        } else {
            // Try to find by transaction mobile numbers
            $filters['receiver_mobile_number'] = $this->customerSearch;
        }

        // Ensure cash transactions are filtered by mobile number
        if (isset($filters['receiver_mobile_number'])) {
            $filters['cash_transaction_mobile_number'] = $filters['receiver_mobile_number'];
        }

        // If no transactions match, return empty results
        $result = $this->getTransactionRepository()->allUnified($filters);
        if (empty($result['transactions'])) {
            $this->transactions = [];
            $this->totals = [];
            return;
        }

        $this->generateTransactionReport($filters);
    }

    private function generateBranchReport($filters)
    {
        // Get branch details
        $branchIds = !empty($this->selectedBranches) ? $this->selectedBranches : $this->branches->pluck('id')->toArray();

        // Calculate sum of clients' wallet balances for selected branches
        $clientsQuery = Customer::query()->where('is_client', true);
        if (!empty($branchIds)) {
            $clientsQuery->whereIn('branch_id', $branchIds);
        }
        $clientsWalletSum = $clientsQuery->sum('balance');

        $this->branchDetails = [
            'safe_balances' => $this->getTotalsService()->getSafeBalances($branchIds),
            'line_balances' => $this->getTotalsService()->getLineBalances($branchIds),
            'clients_wallet_sum' => $clientsWalletSum,
        ];

        // Generate transaction report with branch expenses
        $this->generateTransactionReport($filters);
        $this->totals['total_expenses'] = $this->getTotalsService()->calculateBranchExpenses($filters);

        // Always use backend logic for net profit with expenses for branch report
        $this->totals['net_profit'] = $this->getTotalsService()->calculateNetProfitWithExpenses($filters);
    }

    private function getCustomerSafeBalance($customer)
    {
        // Try to find customer safe (if linked by convention)
        $safe = \App\Models\Domain\Entities\Safe::where('type', 'client')
            ->where('name', 'like', "%{$customer->name}%")
            ->first();

        return $safe ? $safe->current_balance : null;
    }

    public function sortBy($field)
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
        $this->generateReport();
    }

    public function loadMore()
    {
        $this->perPage += 50;
        $this->generateReport();
    }

    public function exportExcel()
    {
        // Use the same transactions as displayed in the UI, with branch_name already set
        $export = new AutoSizeTransactionsExport(collect($this->transactions));
        $filename = $this->reportType . '_report_' . now()->format('Y-m-d_H-i-s') . '.xlsx';
        return \Maatwebsite\Excel\Facades\Excel::download($export, $filename);
    }

    public function exportPdf()
    {
        // Use the same transactions as displayed in the UI, with branch_name already set
        $data = [
            'reportType' => $this->reportType,
            'startDate' => $this->startDate,
            'endDate' => $this->endDate,
            'transactions' => $this->transactions,
            'totals' => $this->totals,
            'customerDetails' => $this->customerDetails,
            'employeeDetails' => $this->employeeDetails,
            'branchDetails' => $this->branchDetails,
        ];
        $html = view('reports.enhanced_pdf', $data)->render();
        $mpdf = new \Mpdf\Mpdf(['mode' => 'utf-8', 'format' => 'A4', 'default_font' => 'dejavusans']);
        $mpdf->WriteHTML($html);
        $filename = $this->reportType . '_report_' . now()->format('Y-m-d_H-i-s') . '.pdf';
        return response()->streamDownload(function () use ($mpdf) {
            echo $mpdf->Output('', 'S');
        }, $filename);
    }

    public function render()
    {
        return view('livewire.reports.enhanced', [
            'lines' => $this->lines,
            'selectedLine' => $this->selectedLine,
            'lineSearch' => $this->lineSearch,
            'filteredLines' => $this->filteredLines,
            'showEmployeeFilter' => in_array($this->reportType, ['transactions', 'employee']),
            'showCustomerFilter' => in_array($this->reportType, ['transactions', 'customer']),
            'showExpenses' => $this->reportType === 'branch',
        ]);
    }
}
