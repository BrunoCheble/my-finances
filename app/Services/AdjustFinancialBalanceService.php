<?php

namespace App\Services;

use App\Enums\FinancialMovementType;
use App\Models\FinancialMovement;
use App\Models\Wallet;

/**
 * Corrects a balance by creating an adjustment movement on the 1st day of the month,
 * instead of editing the balance directly.
 */
class AdjustFinancialBalanceService
{
    public const DESCRIPTION = 'Ajuste de saldo';

    public function __construct(
        private CalculateFinancialBalanceService $calculateFinancialBalanceService
    ) {
    }

    public function execute(int $walletId, string $date, float $amount, string $description = self::DESCRIPTION): ?FinancialMovement
    {
        $amount = round($amount, 2);
        if ($amount == 0) {
            return null;
        }

        $movement = self::makeMovement($walletId, $date, $amount, $description);
        $movement->save();

        $this->calculateFinancialBalanceService->execute($movement->date, $walletId);

        return $movement;
    }

    /**
     * Adjusts the month so its closing balance matches the given real balance.
     */
    public function adjustToRealBalance(int $walletId, string $date, float $realBalance): ?FinancialMovement
    {
        $balance = $this->calculateFinancialBalanceService->execute($date, $walletId);
        $current = $balance ? $balance->calculated_balance : 0;

        return $this->execute($walletId, $date, $realBalance - $current);
    }

    public static function makeMovement(int $walletId, string $date, float $amount, string $description = self::DESCRIPTION): FinancialMovement
    {
        $movement = new FinancialMovement([
            'description' => $description,
            'date' => date('Y-m-01', strtotime($date)),
            'wallet_id' => $walletId,
            'category_id' => null,
            'amount' => round($amount, 2),
            'type' => FinancialMovementType::ADJUSTMENT,
            'include_alert' => false,
        ]);
        $movement->user_id = Wallet::withoutGlobalScopes()->find($walletId)?->user_id;

        return $movement;
    }
}
