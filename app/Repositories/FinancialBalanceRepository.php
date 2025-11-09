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

    public function findByMovements($movements)
    {
        $balancesQuery = FinancialBalance::query();

        $balancesQuery->where(function ($q) use ($movements) {
            foreach ($movements as $movement) {
                $q->orWhere(function ($sub) use ($movement) {
                    $sub->where('wallet_id', $movement['wallet_id'])
                        ->where('start_date', '<=', $movement['date'])
                        ->where('end_date', '>=', $movement['date']);
                });
            }
        });

        return $balancesQuery->get();

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

    public function findPreviousByWalletAndDate(int $walletId, string $date): ?FinancialBalance
    {
        return FinancialBalance::where('wallet_id', $walletId)
            ->where('end_date', '<', $date)
            ->orderBy('end_date', 'desc')
            ->first();
    }
}
