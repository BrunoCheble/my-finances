<?php

namespace App\Services;

use App\Enums\FinancialMovementType;
use App\Models\FinancialBalance;
use App\Models\FinancialMovement;
use DateTime;

class CalculateFinancialBalanceService
{
    static function execute(string $date, int $walletId)
    {
        $balance = self::findOrCreate($date, $walletId);

        $financialMovements = FinancialMovement::where('wallet_id', $balance->wallet_id)
            ->whereBetween('date', [$balance->start_date, $balance->end_date])
            ->get();

        if($financialMovements->isEmpty()) {
            $balance->calculated_balance = $balance->real_balance != 0 ? $balance->real_balance : $balance->initial_balance;
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

        return $balance->save();
    }

    public static function findOrCreate(string $date, int $walletId)
    {
        $balance = FinancialBalance::where('wallet_id', $walletId)
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->first();

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
