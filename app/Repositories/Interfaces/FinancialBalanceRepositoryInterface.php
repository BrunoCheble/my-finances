<?php

namespace App\Repositories\Interfaces;

use App\Models\FinancialBalance;

interface FinancialBalanceRepositoryInterface
{
    public function create(array $data): FinancialBalance;

    public function find(int $id): ?FinancialBalance;

    public function delete(FinancialBalance $balance): bool;

    public function findByWalletAndInterval(int $walletId, string $date): ?FinancialBalance;

    public function findByMovements($movements);

    public function findByWalletAndDates(int $walletId, string $startDate, string $endDate): ?FinancialBalance;

    public function findByWalletByEndDate(int $walletId, string $endDate): ?FinancialBalance;

    public function findPreviousByWalletAndDate(int $walletId, string $date): ?FinancialBalance;
}
