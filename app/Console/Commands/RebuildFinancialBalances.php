<?php

namespace App\Console\Commands;

use App\Models\FinancialBalance;
use App\Models\FinancialMovement;
use App\Models\Wallet;
use App\Services\AdjustFinancialBalanceService;
use App\Services\CalculateFinancialBalanceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Makes balances fully derived from movements.
 *
 * Differences between stored balances and the chain (hand-edited initial_balance,
 * calculated_balance typed in a month without movements) become adjustment movements
 * on the 1st day of the month, preserving the balances shown today. Then every month
 * is recalculated in cascade. Idempotent: running it again creates no new adjustments.
 */
class RebuildFinancialBalances extends Command
{
    protected $signature = 'balances:rebuild
                            {--user= : Processa apenas as carteiras deste user_id}
                            {--dry-run : Mostra o que seria feito e desfaz tudo no final}';

    protected $description = 'Converte ajustes manuais de saldo em movimentos e recalcula todos os saldos em cascata';

    public function handle(CalculateFinancialBalanceService $calculateFinancialBalanceService): int
    {
        $wallets = Wallet::withoutGlobalScopes()
            ->when($this->option('user'), fn ($query, $userId) => $query->where('user_id', $userId))
            ->orderBy('id')
            ->get();

        $dryRun = (bool) $this->option('dry-run');
        $rows = [];

        DB::beginTransaction();
        try {
            foreach ($wallets as $wallet) {
                foreach ($this->convertAdjustments($wallet) as $adjustment) {
                    $rows[] = [$wallet->id, $wallet->name, $adjustment->date, format_currency($adjustment->amount)];
                }
                $this->rebuildWallet($wallet, $calculateFinancialBalanceService);
            }

            $dryRun ? DB::rollBack() : DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Falhou, nada foi alterado: ' . $e->getMessage());
            return self::FAILURE;
        }

        if ($rows) {
            $this->table(['Wallet ID', 'Carteira', 'Data', 'Ajuste'], $rows);
        }
        $this->info(sprintf(
            '%d carteira(s) processada(s), %d ajuste(s) %s.',
            $wallets->count(),
            count($rows),
            $dryRun ? 'seriam criados (dry-run, nada foi gravado)' : 'criados'
        ));

        return self::SUCCESS;
    }

    /**
     * Compares each stored month with the chain and creates adjustments for the differences.
     */
    private function convertAdjustments(Wallet $wallet): array
    {
        $balances = FinancialBalance::withoutGlobalScopes()
            ->where('wallet_id', $wallet->id)
            ->get()
            ->keyBy(fn ($balance) => date('Y-m-01', strtotime($balance->start_date)));

        $adjustments = [];
        $previousClosing = 0;

        foreach ($this->months($wallet) as $month) {
            $movements = FinancialMovement::withoutGlobalScopes()
                ->where('wallet_id', $wallet->id)
                ->whereBetween('date', [$month, date('Y-m-t', strtotime($month))]);
            $movementsTotal = (clone $movements)->sum('amount');
            $hasMovements = $movements->exists();

            $balance = $balances->get($month);
            if (!$balance) {
                // month with movements but no stored balance: only feeds the chain
                $previousClosing += $movementsTotal;
                continue;
            }

            // initial_balance differs from the previous month's closing
            $amount = $balance->initial_balance - $previousClosing;

            // closing balance typed by hand in a month without movements (old edit screen rule).
            // A closing of 0 there is a month opened but still empty (the old "open month" screen), not a balance
            // typed as zero: reading it as typed zeroed every wallet's last month in production.
            $manualClosing = !$hasMovements
                && $balance->total_income == 0
                && $balance->total_expense == 0
                && $balance->total_unidentified == 0
                && (float) $balance->calculated_balance != 0;
            if ($manualClosing) {
                $amount += $balance->calculated_balance - $balance->initial_balance;
            }

            $amount = round($amount, 2);
            if ($amount != 0) {
                $movement = AdjustFinancialBalanceService::makeMovement($wallet->id, $balance->start_date, $amount);
                $movement->save();
                $adjustments[] = $movement;
            }

            $previousClosing = $manualClosing
                ? $balance->calculated_balance
                : $balance->initial_balance + $movementsTotal;
        }

        return $adjustments;
    }

    /**
     * Ensures a balance for every month with movements and recalculates from the first one.
     */
    private function rebuildWallet(Wallet $wallet, CalculateFinancialBalanceService $calculateFinancialBalanceService): void
    {
        $months = $this->months($wallet);

        if ($months->isEmpty()) {
            return;
        }

        foreach ($months as $month) {
            $balance = $calculateFinancialBalanceService->findOrCreate($month, $wallet->id);
            if (!$balance->exists) {
                $balance->save();
            }
        }

        $calculateFinancialBalanceService->recalculateFrom($wallet->id, $months->first());
    }

    /**
     * Months (Y-m-01) with movements or a stored balance, in order.
     */
    private function months(Wallet $wallet)
    {
        return FinancialMovement::withoutGlobalScopes()
            ->where('wallet_id', $wallet->id)
            ->pluck('date')
            ->merge(
                FinancialBalance::withoutGlobalScopes()->where('wallet_id', $wallet->id)->pluck('start_date')
            )
            ->map(fn ($date) => date('Y-m-01', strtotime($date)))
            ->unique()
            ->sort()
            ->values();
    }
}
