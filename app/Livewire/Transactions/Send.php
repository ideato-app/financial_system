<?php

namespace App\Livewire\Transactions;

use Livewire\Component;
use Livewire\Attributes\Validate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\Domain\Entities\Customer;
use App\Models\Domain\Entities\Line;
use App\Models\Domain\Entities\Safe;
use App\Application\UseCases\CreateTransaction;

class Send extends Component
{
    // Client Information
    #[Validate('required|string|max:20')]
    public $clientMobile = '';

    #[Validate('required|string|max:255')]
    public $clientName = '';

    #[Validate('nullable|in:male,female')]
    public $clientGender = '';

    public $clientCode = '';
    public $clientId = null;
    public $clientBalance = 0;

    // Receiver Information
    #[Validate('required|string|max:20')]
    public $receiverMobile = '';

    // Transaction Details
    #[Validate('required|numeric|min:5|multiple_of:5')]
    public $amount = 0;

    public $commission = 0;

    #[Validate('nullable|numeric|min:0')]
    public $discount = 0;

    #[Validate('required_if:discount,>0')]
    public $discountNotes = '';

    // Line Selection
    #[Validate('required|exists:lines,id')]
    public $selectedLineId = '';

    public $availableLines = [];

    // Payment Options
    public $collectFromClientSafe = false;
    public $collectFromCustomerWallet = false;
    public $deductFromLineOnly = true;

    // UI State
    public $clientSuggestions = [];
    public $lowBalanceWarning = '';
    public $successMessage = '';
    public $errorMessage = '';

    // Form validation messages
    protected $messages = [
        'amount.multiple_of' => 'Amount must be a multiple of 5 EGP.',
        'amount.min' => 'Minimum amount is 5 EGP.',
        'clientMobile.required' => 'Client mobile number is required.',
        'clientName.required' => 'Client name is required.',
        'receiverMobile.required' => 'Receiver mobile number is required.',
        'selectedLineId.required' => 'Please select an available line.',
        'selectedLineId.exists' => 'Selected line is not valid.',
        'discountNotes.required_if' => 'Discount notes are required when discount is provided.',
    ];

    public function mount()
    {
        $this->loadAvailableLines();
    }

    public function updatedClientMobile()
    {
        $this->searchClient();
    }

    public function updatedAmount()
    {
        $this->calculateCommission();
        $this->checkLineBalance();
    }

    public function updatedDiscount()
    {
        $this->calculateCommission();
    }

    public function updatedSelectedLineId()
    {
        $this->checkLineBalance();
    }

    public function updatedCollectFromClientSafe()
    {
        if ($this->collectFromClientSafe) {
            $this->collectFromCustomerWallet = false;
            $this->deductFromLineOnly = false;
        } else {
            $this->deductFromLineOnly = !$this->collectFromCustomerWallet;
        }
        $this->checkLineBalance();
    }

    public function updatedCollectFromCustomerWallet()
    {
        if ($this->collectFromCustomerWallet) {
            $this->collectFromClientSafe = false;
            $this->deductFromLineOnly = false;
        } else {
            $this->deductFromLineOnly = !$this->collectFromClientSafe;
        }
        $this->checkLineBalance();
    }

    private function searchClient()
    {
        if (strlen($this->clientMobile) >= 3) {
            $clients = Customer::where('mobile_number', 'like', '%' . $this->clientMobile . '%')
                ->limit(5)
                ->get(['id', 'name', 'mobile_number', 'customer_code', 'gender', 'balance']);

            $this->clientSuggestions = $clients->toArray();

            // Auto-fill if exact match
            $exactMatch = $clients->where('mobile_number', $this->clientMobile)->first();
            if ($exactMatch) {
                $this->selectClient($exactMatch->id);
            }
        } else {
            $this->clientSuggestions = [];
        }
    }

    public function selectClient($clientId)
    {
        $client = Customer::find($clientId);
        if ($client) {
            $this->clientId = $client->id;
            $this->clientName = $client->name;
            $this->clientMobile = $client->mobile_number;
            $this->clientCode = $client->customer_code ?: $this->generateClientCode();
            $this->clientGender = $client->gender;
            $this->clientBalance = $client->balance;
            $this->clientSuggestions = [];
        }
    }

    private function generateClientCode()
    {
        // Generate unique client code based on current timestamp and random number
        do {
            $code = 'C' . date('ym') . str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
        } while (Customer::where('customer_code', $code)->exists());

        return $code;
    }

    private function calculateCommission()
    {
        $amount = (float) $this->amount;
        $discount = (float) $this->discount;

        if ($amount <= 0) {
            $this->commission = 0;
            return;
        }

        // Commission: 5 EGP per 500 EGP (no fractions)
        $baseCommission = floor($amount / 500) * 5;
        $this->commission = max(0, $baseCommission - $discount);
    }

    private function loadAvailableLines()
    {
        $userBranchId = Auth::user()->branch_id;

        $this->availableLines = Line::where('branch_id', $userBranchId)
            ->where('status', 'active')
            ->get(['id', 'mobile_number', 'current_balance', 'network'])
            ->map(function ($line) {
                return [
                    'id' => $line->id,
                    'mobile_number' => $line->mobile_number,
                    'current_balance' => $line->current_balance,
                    'network' => $line->network,
                    'display' => $line->mobile_number . ' (' . number_format($line->current_balance, 2) . ' EGP) - ' . ucfirst($line->network),
                ];
            })
            ->toArray();
    }

    private function checkLineBalance()
    {
        $this->lowBalanceWarning = '';

        if (!$this->selectedLineId || !$this->amount) {
            return;
        }

        $line = collect($this->availableLines)->firstWhere('id', $this->selectedLineId);
        if (!$line) {
            return;
        }

        $amount = (float) $this->amount;
        $clientBalance = (float) $this->clientBalance;
        $requiredAmount = $amount;

        if ($this->collectFromClientSafe && $clientBalance > 0) {
            // If collecting from client safe, reduce required amount from line
            $requiredAmount = max(0, $amount - $clientBalance);
        } elseif ($this->collectFromCustomerWallet && $clientBalance > 0) {
            // If collecting from customer wallet, reduce required amount from line
            $requiredAmount = max(0, $amount - $clientBalance);
        }

        if ($line['current_balance'] < $requiredAmount) {
            $this->lowBalanceWarning = "Insufficient balance in selected line. Available: " .
                number_format($line['current_balance'], 2) . " EGP, Required: " .
                number_format($requiredAmount, 2) . " EGP. Please choose another line.";
        }
    }

    public function submitTransaction()
    {
        $this->validate();

        // Cast to proper types for arithmetic operations
        $amount = (float) $this->amount;
        $commission = (float) $this->commission;
        $discount = (float) $this->discount;
        $clientBalance = (float) $this->clientBalance;

        // Additional validation
        if ($amount + $commission - $discount <= 0) {
            $this->errorMessage = 'Invalid transaction amount after commission and discount.';
            return;
        }

        if (($this->collectFromClientSafe || $this->collectFromCustomerWallet) && $clientBalance < $amount) {
            $this->errorMessage = 'Client balance is insufficient for this transaction.';
            return;
        }

        if ($this->lowBalanceWarning) {
            $this->errorMessage = 'Please resolve balance issues before submitting.';
            return;
        }

        try {
            DB::transaction(function () use ($amount, $commission, $discount) {
                // Create or update client
                if (!$this->clientId) {
                    $client = Customer::create([
                        'name' => $this->clientName,
                        'mobile_number' => $this->clientMobile,
                        'customer_code' => $this->clientCode ?: $this->generateClientCode(),
                        'gender' => $this->clientGender ?: 'male',
                        'balance' => 0,
                        'is_client' => true,
                        'agent_id' => Auth::id(),
                        'branch_id' => Auth::user()->branch_id,
                    ]);
                    $this->clientId = $client->id;
                } else {
                    // Update existing client
                    Customer::where('id', $this->clientId)->update([
                        'name' => $this->clientName,
                        'gender' => $this->clientGender,
                        'customer_code' => $this->clientCode,
                    ]);
                }

                // Get selected line for transaction
                $line = Line::find($this->selectedLineId);
                if (!$line) {
                    throw new \Exception('Selected line not found.');
                }

                $safe = $line->branch->safe;
                if (!$safe) {
                    // Try to find any safe for this branch as fallback
                    $safe = Safe::where('branch_id', $line->branch_id)->first();
                    if (!$safe) {
                        throw new \Exception('No safe found for this branch.');
                    }
                }

                // Create transaction using the CreateTransaction use case
                app(CreateTransaction::class)->execute(
                    customerName: $this->clientName,
                    customerMobileNumber: $this->clientMobile,
                    customerCode: $this->clientCode,
                    amount: $amount,
                    commission: $commission,
                    deduction: $discount,
                    transactionType: 'Transfer',
                    agentId: Auth::id(),
                    lineId: $this->selectedLineId,
                    safeId: $safe->id,
                    isAbsoluteWithdrawal: false,
                    paymentMethod: $this->getPaymentMethod(),
                    gender: $this->clientGender ?: 'male',
                    isClient: true,
                    receiverMobileNumber: $this->receiverMobile,
                    discountNotes: $this->discountNotes,
                    notes: null
                );
            });

            // Success
            $this->successMessage = 'Transaction created successfully!';
            $this->resetForm();

            // Redirect after a short delay
            $this->js('setTimeout(() => window.location.href = "' . route('transactions.index') . '", 2000)');
        } catch (\Exception $e) {
            $this->errorMessage = 'Failed to create transaction: ' . $e->getMessage();
        }
    }

    private function getPaymentMethod()
    {
        if ($this->collectFromClientSafe) {
            return 'client safe';
        } elseif ($this->collectFromCustomerWallet) {
            return 'client wallet';
        } else {
            return 'line balance';
        }
    }

    private function resetForm()
    {
        $this->reset([
            'clientMobile',
            'clientName',
            'clientGender',
            'clientCode',
            'clientId',
            'clientBalance',
            'receiverMobile',
            'amount',
            'commission',
            'discount',
            'discountNotes',
            'selectedLineId',
            'collectFromClientSafe',
            'collectFromCustomerWallet',
            'deductFromLineOnly',
            'clientSuggestions',
            'lowBalanceWarning',
            'errorMessage'
        ]);
        $this->deductFromLineOnly = true;
        $this->loadAvailableLines();
    }

    public function render()
    {
        return view('livewire.transactions.send');
    }
}
