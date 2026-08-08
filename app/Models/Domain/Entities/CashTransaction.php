<?php

namespace App\Models\Domain\Entities;

use Illuminate\Database\Eloquent\Model;

class CashTransaction extends Model
{
    protected $table = 'cash_transactions';

    protected $fillable = [
        'customer_name',
        'customer_code',
        'amount',
        'notes',
        'safe_id',
        'transaction_type',
        'status',
        'transaction_date_time',
        'depositor_national_id',
        'depositor_mobile_number',
        'agent_id',
        'destination_branch_id',
        'destination_safe_id',
        'reference_number',
        'approved_at',
        'approved_by',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
    ];

    protected $casts = [
        'transaction_date_time' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'amount' => 'decimal:2',
    ];

    public function getCommissionAttribute()
    {
        return 0;
    }

    public function agent()
    {
        return $this->belongsTo(\App\Domain\Entities\User::class, 'agent_id');
    }

    public function safe()
    {
        return $this->belongsTo(\App\Models\Domain\Entities\Safe::class, 'safe_id');
    }

    /**
     * Get a descriptive transaction name based on the transaction type and customer information
     */
    public function getDescriptiveTransactionNameAttribute()
    {
        $type = $this->transaction_type;
        $customerName = $this->customer_name;
        
        // Determine the deposit/withdrawal type based on customer name and other indicators
        if ($type === 'Deposit') {
            if ($customerName === 'اداري') {
                return 'إيداع نقدي إداري';
            } elseif ($this->customer_code) {
                return 'إيداع نقدي لمحفظة العميل';
            } elseif ($this->depositor_national_id || $this->depositor_mobile_number) {
                return 'إيداع نقدي مباشر';
            } else {
                return 'إيداع نقدي';
            }
        } elseif ($type === 'Withdrawal') {
            if ($customerName === 'اداري') {
                return 'سحب نقدي إداري';
            } elseif ($this->customer_code) {
                return 'سحب نقدي من محفظة العميل';
            } else {
                return 'سحب نقدي';
            }
        }
        
        return ucfirst($type);
    }

    // Future relationships and methods can be added here
} 