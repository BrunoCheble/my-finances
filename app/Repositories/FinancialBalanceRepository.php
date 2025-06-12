<?php

namespace App\Repositories;

use App\Models\FinancialBalance;
use App\Repositories\Interfaces\FinancialBalanceRepositoryInterface;

class FinancialBalanceRepository implements FinancialBalanceRepositoryInterface
{
    public function create(array $data): FinancialBalance
    {
        return FinancialBalance::create($data);
    }

    public function delete(FinancialBalance $balance): bool
    {
        return $balance->delete();
    }

    public function find(int $id): ?FinancialBalance
    {
        return FinancialBalance::find($id);
    }

    public function findByWalletAndInterval(int $walletId, string $date): ?FinancialBalance
    {
        return FinancialBalance::where('wallet_id', $walletId)
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->first();
    }

    public function findByWalletAndDates(int $walletId, string $startDate, string $endDate): ?FinancialBalance
    {
        return FinancialBalance::where('wallet_id', $walletId)
            ->where('start_date', $startDate)
            ->where('end_date', $endDate)
            ->first();
    }

    public function findByWalletByEndDate(int $walletId, string $endDate): ?FinancialBalance
    {
        return FinancialBalance::where('wallet_id', $walletId)
            ->where('end_date', $endDate)
            ->first();
    }
}
