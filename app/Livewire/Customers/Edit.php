<?php

namespace App\Livewire\Customers;

use App\Application\UseCases\UpdateCustomer;
use App\Domain\Interfaces\CustomerRepository;
use Livewire\Component;
use Livewire\Attributes\Validate;
use Illuminate\Support\Facades\Gate;
use App\Domain\Interfaces\UserRepository;
use App\Domain\Interfaces\BranchRepository;
use App\Domain\Entities\User;
use Illuminate\Database\Eloquent\Collection;

class Edit extends Component
{
    public $customer;
    public $customerId;

    public $name = '';
    public $mobileNumbers = [];
    public $customerCode = '';
    public $gender = '';
    public $balance = 0.00;
    public $is_client = false;
    public $agent_id = null;
    public $branch_id = '';

    public Collection $agents;
    public Collection $branches;

    private CustomerRepository $customerRepository;
    private UpdateCustomer $updateCustomerUseCase;
    private UserRepository $userRepository;
    private BranchRepository $branchRepository;

    public function boot(
        CustomerRepository $customerRepository,
        UpdateCustomer $updateCustomerUseCase,
        UserRepository $userRepository,
        BranchRepository $branchRepository
    )
    {
        $this->customerRepository = $customerRepository;
        $this->updateCustomerUseCase = $updateCustomerUseCase;
        $this->userRepository = $userRepository;
        $this->branchRepository = $branchRepository;
    }

    protected function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'mobileNumbers' => 'required|array|min:1',
            'mobileNumbers.*' => 'required|string|max:20|distinct',
            'customerCode' => 'nullable|string|max:255',
            'gender' => 'required|in:male,female',
            'balance' => 'required|numeric|min:0',
            'is_client' => 'boolean',
            'agent_id' => 'nullable|exists:users,id',
            'branch_id' => 'required|exists:branches,id',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'name' => 'Customer Name',
            'mobileNumber' => 'Mobile Number',
            'customerCode' => 'Customer Code',
            'gender' => 'Gender',
            'balance' => 'Balance',
            'is_client' => 'Is Client',
            'agent_id' => 'Agent',
            'branch_id' => 'Branch',
        ];
    }

    public function mount($customerId)
    {
        Gate::authorize('manage-customers');
        $this->customerId = $customerId;
        $this->customer = $this->customerRepository->findById($customerId);

        if ($this->customer) {
            $this->name = $this->customer->name;
            $this->mobileNumbers = $this->customer->mobileNumbers->pluck('mobile_number')->toArray();
            if (empty($this->mobileNumbers)) {
                $this->mobileNumbers = [$this->customer->mobile_number];
            }
            $this->customerCode = $this->customer->customer_code;
            $this->gender = $this->customer->gender;
            $this->balance = $this->customer->balance;
            $this->is_client = $this->customer->is_client;
            $this->agent_id = $this->customer->agent_id;
            $this->branch_id = $this->customer->branch_id;

            $this->agents = User::role('agent')->get();
            $this->branches = $this->branchRepository->all();
        } else {
            abort(404);
        }
    }

    public function addMobileNumber()
    {
        $this->mobileNumbers[] = '';
    }

    public function removeMobileNumber($index)
    {
        unset($this->mobileNumbers[$index]);
        $this->mobileNumbers = array_values($this->mobileNumbers);
    }

    public function updateCustomer()
    {
        $this->validate();

        try {
            // Use the first mobile number as the primary
            $primaryMobile = $this->mobileNumbers[0];
            $this->updateCustomerUseCase->execute(
                $this->customerId,
                $this->name,
                $primaryMobile,
                $this->customerCode,
                $this->gender,
                $this->balance,
                $this->is_client,
                $this->agent_id,
                $this->branch_id
            );
            // Sync mobile numbers
            $this->customer->mobileNumbers()->delete();
            foreach ($this->mobileNumbers as $number) {
                $this->customer->mobileNumbers()->create(['mobile_number' => $number]);
            }
            session()->flash('message', 'Customer updated successfully.');
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to update customer: ' . $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.customers.edit');
    }
}
