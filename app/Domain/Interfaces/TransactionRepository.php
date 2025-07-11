<?php

namespace App\Domain\Interfaces;

use App\Models\Domain\Entities\Transaction;

interface TransactionRepository
{
    public function create(array $attributes): Transaction;
    public function findById(string $id): ?Transaction;
    public function update(string $id, array $attributes): Transaction;
    public function delete(string $id): void;
    public function all(): array;
    public function findByStatus(string $status, ?int $branchId = null): array;
    public function save(Transaction $transaction): Transaction;
    public function getTransactionsByLineAndDateRange(string $lineId, \Carbon\Carbon $startDate, \Carbon\Carbon $endDate);
    public function filter(array $filters): array;
    public function getTotalReceivedForLine(string $lineId, \Carbon\Carbon $startDate, \Carbon\Carbon $endDate): float;
    public function getTotalSentForLine(string $lineId, \Carbon\Carbon $startDate, \Carbon\Carbon $endDate): float;
    /**
     * Fetch all transactions (ordinary + cash) as a unified list for UI and logic.
     */
    public function allUnified(array $filters = []): array;
}
