<?php

namespace App\Services;

use App\Enums\FinancialMovementType;
use App\Models\FinancialBalance;
use App\Repositories\Interfaces\FinancialBalanceRepositoryInterface;
use App\Repositories\Interfaces\FinancialMovementRepositoryInterface;

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

    public function execute(string $date, int $walletId)
    {
        $balance = $this->findOrCreate($date, $walletId);

        $financialMovements = $this->financialMovementRepository->findByWalletAndInterval(
            $balance->wallet_id,
            $balance->start_date,
            $balance->end_date
        );

        if ($financialMovements->isEmpty() && $balance->initial_balance == 0 && $balance->real_balance == 0) {
            return $balance->delete();
        }

        if ($financialMovements->isEmpty()) {
            $balance->calculated_balance = $balance->real_balance != 0 ? $balance->real_balance : $balance->initial_balance;
            $balance->total_expense = 0;
            $balance->total_income = 0;
            $balance->total_unidentified = 0;
            return $balance->save();
        }

        $balance->total_expense = 0;
        $balance->total_income = 0;
        $balance->total_unidentified = 0;

        foreach ($financialMovements as $movement) {
            switch ($movement->type) {
                case FinancialMovementType::TRANSFER:
                    $balance->total_unidentified += $movement->amount;
                    break;
                case FinancialMovementType::EXPENSE:
                    $balance->total_expense += $movement->amount;
                    break;
                case FinancialMovementType::INCOME:
                    $balance->total_income += $movement->amount;
                    break;
                case FinancialMovementType::REFUND:
                    $balance->total_expense -= $movement->amount;
                    break;
                case FinancialMovementType::DISCOUNT:
                    $balance->total_income -= $movement->amount;
                    break;
            }
        }

        $balance->total_income = $balance->total_income;
        $diff = $balance->total_income - $balance->total_expense + $balance->total_unidentified;
        $balance->calculated_balance = $balance->initial_balance + $diff;

        if ($balance->real_balance == 0) {
            $balance->real_balance = $balance->calculated_balance;
        }

        return $balance->save();
    }

    public function findOrCreate(string $date, int $walletId)
    {
        $balance = $this->balanceRepository->findByWalletAndInterval($walletId, $date);

        if ($balance) {
            return $balance;
        }

        $balance = new FinancialBalance();
        $balance->wallet_id = $walletId;
        $balance->start_date = date('Y-m-01', strtotime($date));
        $balance->end_date = date('Y-m-t', strtotime($date));
        $balance->initial_balance = 0;
        $balance->total_expense = 0;
        $balance->total_income = 0;
        $balance->total_unidentified = 0;
        $balance->calculated_balance = 0;

        return $balance;
    }
}
