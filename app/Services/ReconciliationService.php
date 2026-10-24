<?php

namespace App\Services;

use App\Models\FinancialBalance;
use App\Models\FinancialMovement;
use App\Models\MovementChange;
use App\Models\StatementImport;
use Illuminate\Support\Collection;

/**
 * "Conferir mês": the user confirms a wallet's closing balance of a month.
 * Balances stay derived; the confirmed value is kept aside so a later change
 * (that cascades into the month) shows up as a divergence instead of going unnoticed.
 */
class ReconciliationService
{
    public const SOURCE_MANUAL = 'manual';
    public const SOURCE_STATEMENT = 'statement';

    public function reconcile(FinancialBalance $balance, string $source = self::SOURCE_MANUAL, ?float $value = null): FinancialBalance
    {
        $balance->forceFill([
            'reconciled_balance' => round($value ?? (float) $balance->calculated_balance, 2),
            'reconciled_at' => now(),
            'reconciled_source' => $source,
        ])->save();

        return $balance;
    }

    public function unreconcile(FinancialBalance $balance): void
    {
        $balance->forceFill(['reconciled_balance' => null, 'reconciled_at' => null, 'reconciled_source' => null])->save();
    }

    /**
     * Brings the month back to the confirmed balance with an adjustment movement.
     */
    public function restore(FinancialBalance $balance, AdjustFinancialBalanceService $adjust): ?FinancialMovement
    {
        if (!$balance->is_diverged) {
            return null;
        }

        return $adjust->execute(
            $balance->wallet_id,
            $balance->start_date,
            -$balance->reconciliation_difference,
            __('Adjustment back to the confirmed balance')
        );
    }

    /**
     * Last confirmed month ('Y-m') per wallet id. A change dated in or before it alters a confirmed balance.
     */
    public static function lastReconciledMonths(): array
    {
        return FinancialBalance::whereNotNull('reconciled_balance')
            ->selectRaw('wallet_id, MAX(start_date) as month')
            ->groupBy('wallet_id')
            ->pluck('month', 'wallet_id')
            ->map(fn ($date) => substr($date, 0, 7))
            ->all();
    }

    /**
     * The confirmed month a change on this wallet/date would alter (the latest one), or null.
     */
    public static function affectedBalance(int $walletId, string $date): ?FinancialBalance
    {
        return FinancialBalance::withoutGlobalScopes()
            ->where('wallet_id', $walletId)
            ->whereNotNull('reconciled_balance')
            ->where('end_date', '>=', date('Y-m-d', strtotime($date)))
            ->orderByDesc('start_date')
            ->first();
    }

    public static function diverged(): Collection
    {
        return FinancialBalance::with('wallet')
            ->whereNotNull('reconciled_balance')
            ->whereRaw('ABS(calculated_balance - reconciled_balance) >= 0.005')
            ->orderByDesc('start_date')
            ->get();
    }

    /**
     * Changes made after the month was confirmed that affect its balance:
     * any change of the wallet dated in that month or before.
     */
    public static function changesSince(FinancialBalance $balance): Collection
    {
        if (!$balance->reconciled_at) {
            return collect();
        }

        return MovementChange::where('wallet_id', $balance->wallet_id)
            ->where('month', '<=', substr($balance->start_date, 0, 7))
            ->where('created_at', '>=', $balance->reconciled_at)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Logs a movement change when it alters a confirmed balance. Called from the model events.
     */
    public static function log(FinancialMovement $movement, string $action): void
    {
        $positions = match ($action) {
            'updated' => collect([
                ['wallet_id' => $movement->getOriginal('wallet_id'), 'date' => $movement->getOriginal('date')],
                ['wallet_id' => $movement->wallet_id, 'date' => $movement->date],
            ])->unique(fn ($p) => $p['wallet_id'] . substr($p['date'], 0, 7)),
            default => collect([['wallet_id' => $movement->wallet_id, 'date' => $movement->date]]),
        };

        foreach ($positions as $position) {
            if (!self::affectedBalance((int) $position['wallet_id'], $position['date'])) {
                continue;
            }

            $change = new MovementChange([
                'wallet_id' => $position['wallet_id'],
                'month' => substr($position['date'], 0, 7),
                'movement_id' => $action === 'deleted' ? null : $movement->id,
                'action' => $action,
                'description' => mb_substr((string) $movement->description, 0, 255),
                'date' => $position['date'],
                'old_amount' => $action === 'created' ? null : $movement->getOriginal('amount'),
                'new_amount' => $action === 'deleted' ? null : $movement->amount,
            ]);
            $change->user_id = $movement->user_id;
            $change->save();
        }
    }

    /**
     * After an import: confirm the month when the wallet matches the bank's closing balance.
     */
    public function reconcileFromStatement(StatementImport $import): ?FinancialBalance
    {
        if ($import->closing_balance === null || !$import->period_end || !$import->wallet_id) {
            return null;
        }

        $end = date('Y-m-d', strtotime($import->period_end));
        $balance = FinancialBalance::where('wallet_id', $import->wallet_id)
            ->where('start_date', date('Y-m-01', strtotime($end)))
            ->first();
        if (!$balance) {
            return null;
        }

        // a statement ending mid-month only matches when nothing was registered after it
        $later = FinancialMovement::where('wallet_id', $import->wallet_id)
            ->where('date', '>', $end)
            ->where('date', '<=', $balance->end_date)
            ->exists();
        if ($later || abs((float) $balance->calculated_balance - (float) $import->closing_balance) >= 0.005) {
            return null;
        }

        return $this->reconcile($balance, self::SOURCE_STATEMENT, (float) $import->closing_balance);
    }
}
