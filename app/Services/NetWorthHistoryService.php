<?php

namespace App\Services;

use App\Models\FinancialBalance;
use App\Models\Wallet;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Net worth month by month: each wallet carries its latest balance forward
 * through months without movements. Held amounts (reserved_amount) are a
 * snapshot of today, so they only apply to the last month.
 */
class NetWorthHistoryService
{
    public const PERIODS = ['6m' => 6, '1y' => 12, '2y' => 24, 'all' => null];

    /**
     * @return array{months: string[], total: float[], wallets: Collection}
     */
    public static function build(?int $lastMonths = null): array
    {
        $balances = FinancialBalance::orderBy('start_date')->get(['wallet_id', 'start_date', 'calculated_balance']);
        if ($balances->isEmpty()) {
            return ['months' => [], 'total' => [], 'wallets' => collect()];
        }

        $first = Carbon::parse($balances->first()->start_date)->startOfMonth();
        $last = Carbon::parse($balances->last()->start_date)->startOfMonth();
        $months = [];
        for ($month = $first->copy(); $month <= $last; $month->addMonth()) {
            $months[] = $month->format('Y-m');
        }
        if ($lastMonths) {
            // one extra month before the period is the starting point of the change
            $months = array_slice($months, -($lastMonths + 1));
        }

        $byWallet = $balances->groupBy('wallet_id');
        $wallets = Wallet::whereIn('id', $byWallet->keys())->get()->keyBy('id');

        $series = $wallets->map(function (Wallet $wallet) use ($byWallet, $months) {
            $walletBalances = $byWallet[$wallet->id]->keyBy(fn ($balance) => substr($balance->start_date, 0, 7));
            $carry = (float) ($byWallet[$wallet->id]->filter(fn ($b) => substr($b->start_date, 0, 7) < $months[0])->last()?->calculated_balance ?? 0);

            $values = [];
            foreach ($months as $month) {
                $carry = isset($walletBalances[$month]) ? (float) $walletBalances[$month]->calculated_balance : $carry;
                $values[] = $carry;
            }
            $values[count($values) - 1] += (float) $wallet->reserved_amount;

            return (object) [
                'id' => $wallet->id,
                'name' => $wallet->name,
                'color' => $wallet->color ?? '#94a3b8',
                'third_party' => $wallet->is_third_party,
                'values' => array_map(fn ($value) => round($value, 2), $values),
            ];
        })->sortByDesc(fn ($wallet) => end($wallet->values))->values();

        // money lent to people is still the user's: shown as its own line
        $ends = array_map(fn ($month) => Carbon::createFromFormat('Y-m-d', $month . '-01')->endOfMonth()->toDateString(), $months);
        $toReceive = array_values(LoanService::outstandingAt($ends));
        if (array_filter($toReceive)) {
            $series->push((object) [
                'id' => 'loans',
                'name' => __('Loans to receive'),
                'color' => '#d97706',
                'third_party' => false,
                'loans' => true,
                'values' => $toReceive,
            ]);
        }

        $total = [];
        foreach (array_keys($months) as $i) {
            $total[] = round($series->sum(fn ($wallet) => $wallet->values[$i]), 2);
        }

        return ['months' => $months, 'total' => $total, 'wallets' => $series];
    }
}
