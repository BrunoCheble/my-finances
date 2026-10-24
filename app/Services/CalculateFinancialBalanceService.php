<?php

namespace App\Services;

use App\Enums\FinancialMovementType;
use App\Models\FinancialBalance;
use App\Models\Wallet;
use App\Repositories\Interfaces\FinancialBalanceRepositoryInterface;
use App\Repositories\Interfaces\FinancialMovementRepositoryInterface;
use Illuminate\Support\Collection;

/**
 * Monthly balances are fully derived from movements:
 * a month's initial_balance = the wallet's previous month calculated_balance.
 * Balance corrections are made with "adjustment" movements.
 */
class CalculateFinancialBalanceService
{
    private FinancialMovementRepositoryInterface $financialMovementRepository;
    private FinancialBalanceRepositoryInterface $balanceRepository;

    public function __construct(
        FinancialMovementRepositoryInterface $financialMovementRepository,
        FinancialBalanceRepositoryInterface $balanceRepository,
    ) {
        $this->financialMovementRepository = $financialMovementRepository;
        $this->balanceRepository = $balanceRepository;
    }

    /**
     * Recalculates the date's month and every following month of the wallet.
     * Returns the date's month balance (null if the month became empty and was removed).
     */
    public function execute(string $date, int $walletId): ?FinancialBalance
    {
        return $this->recalculateFrom($walletId, $date)->first(
            fn ($balance) => $balance->start_date <= date('Y-m-d', strtotime($date))
        );
    }

    /**
     * Recalculates from the earliest date of each affected wallet.
     * Accepts movements (or arrays) with wallet_id and date.
     */
    public function executeForMovements(iterable $movements): Collection
    {
        $earliestByWallet = [];
        foreach ($movements as $movement) {
            $walletId = $movement['wallet_id'];
            $date = date('Y-m-d', strtotime($movement['date']));
            if (!isset($earliestByWallet[$walletId]) || $date < $earliestByWallet[$walletId]) {
                $earliestByWallet[$walletId] = $date;
            }
        }

        $balances = collect();
        foreach ($earliestByWallet as $walletId => $date) {
            $balances = $balances->merge($this->recalculateFrom($walletId, $date));
        }
        return $balances;
    }

    public function recalculateFrom(int $walletId, string $date): Collection
    {
        $balance = $this->findOrCreate($date, $walletId);
        $balances = collect([$balance])->merge(
            $this->balanceRepository->findNextByWalletAndDate($walletId, $balance->end_date)
        );

        $previous = $this->balanceRepository->findPreviousByWalletAndDate($walletId, $balance->start_date);
        $initial = $previous ? $previous->calculated_balance : 0;

        $recalculated = collect();
        foreach ($balances as $current) {
            $current->initial_balance = $initial;
            $this->recalculate($current);
            $initial = $current->calculated_balance;

            if ($current->exists) {
                $recalculated->push($current);
            }
        }

        return $recalculated;
    }

    private function recalculate(FinancialBalance $balance): void
    {
        $financialMovements = $this->financialMovementRepository->findByWalletAndInterval(
            $balance->wallet_id,
            $balance->start_date,
            $balance->end_date
        );

        $balance->total_expense = 0;
        $balance->total_income = 0;
        $balance->total_unidentified = 0;
        $balance->calculated_balance = $balance->initial_balance;

        // a month with no movements and no carried balance doesn't need to exist
        if ($financialMovements->isEmpty() && $balance->initial_balance == 0) {
            if ($balance->exists) {
                $balance->delete();
            }
            return;
        }

        foreach ($financialMovements as $movement) {
            switch ($movement->type) {
                case FinancialMovementType::LOAN:
                case FinancialMovementType::TRANSFER:
                case FinancialMovementType::ADJUSTMENT:
                    $balance->total_unidentified += $movement->amount;
                    break;
                case FinancialMovementType::EXPENSE:
                case FinancialMovementType::REFUND:
                    $balance->total_expense += $movement->amount;
                    break;
                case FinancialMovementType::INCOME:
                case FinancialMovementType::DISCOUNT:
                    $balance->total_income += $movement->amount;
                    break;
            }
        }

        $diff = $balance->total_income + $balance->total_expense + $balance->total_unidentified;
        $balance->calculated_balance = round($balance->initial_balance + $diff, 2);

        $balance->save();
    }

    public function findOrCreate(string $date, int $walletId): FinancialBalance
    {
        $balance = $this->balanceRepository->findByWalletAndInterval($walletId, $date);

        if ($balance) {
            return $balance;
        }
        $previousBalance = $this->balanceRepository->findPreviousByWalletAndDate($walletId, $date);

        $balance = new FinancialBalance();
        // explicit so it also works outside an authenticated request (console commands)
        $balance->user_id = Wallet::withoutGlobalScopes()->find($walletId)?->user_id;
        $balance->wallet_id = $walletId;
        $balance->start_date = date('Y-m-01', strtotime($date));
        $balance->end_date = date('Y-m-t', strtotime($date));
        $balance->initial_balance = $previousBalance ? $previousBalance->calculated_balance : 0;
        $balance->total_expense = 0;
        $balance->total_income = 0;
        $balance->total_unidentified = 0;
        $balance->calculated_balance = 0;

        return $balance;
    }
}
