<?php

namespace App\Services;

use App\Models\FinancialBalance;
use App\Models\Wallet;
use Carbon\Carbon;

/**
 * Every active wallet gets a monthly snapshot up to the current month, carrying the
 * previous closing balance through months without movements. Replaces opening the
 * months by hand, so each month lists all wallets and its total matches the net worth.
 */
class OpenMonthsService
{
    public function __construct(private CalculateFinancialBalanceService $calculateFinancialBalanceService)
    {
    }

    /**
     * @return int snapshots created
     */
    public function execute(?Carbon $until = null): int
    {
        $until = ($until ?? now())->copy()->startOfMonth();
        $created = 0;

        $wallets = Wallet::where('active', true)->pluck('id');
        $existing = FinancialBalance::whereIn('wallet_id', $wallets)->get(['wallet_id', 'start_date'])
            ->groupBy('wallet_id')
            ->map(fn ($balances) => $balances->map(fn ($b) => substr($b->start_date, 0, 7))->flip());

        foreach ($existing as $walletId => $months) {
            $firstMissing = null;
            $month = Carbon::createFromFormat('Y-m-d', $months->keys()->min() . '-01');

            for (; $month <= $until; $month->addMonth()) {
                $key = $month->format('Y-m');
                if (isset($months[$key])) {
                    continue;
                }
                $balance = $this->calculateFinancialBalanceService->findOrCreate($month->toDateString(), $walletId);
                // a wallet with nothing carried has nothing to show
                if ((float) $balance->initial_balance == 0) {
                    continue;
                }
                $balance->calculated_balance = $balance->initial_balance;
                $balance->save();
                $firstMissing ??= $month->toDateString();
                $created++;
            }

            if ($firstMissing) {
                $this->calculateFinancialBalanceService->recalculateFrom($walletId, $firstMissing);
            }
        }

        return $created;
    }
}
