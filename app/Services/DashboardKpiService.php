<?php

namespace App\Services;

use App\Models\FinancialBalance;
use Carbon\Carbon;

/**
 * Headline numbers of the dashboard for a month ("Y-m").
 */
class DashboardKpiService
{
    public static function for(string $month, array $monthlySummary): array
    {
        $end = Carbon::createFromFormat('Y-m-d', $month . '-01')->endOfMonth()->toDateString();
        $previous = Carbon::createFromFormat('Y-m-d', $month . '-01')->subMonth()->format('Y-m');

        // latest balance of every wallet up to the month (a wallet may have no movement in it)
        $latest = FinancialBalance::with('wallet')
            ->where('start_date', '<=', $end)
            ->get()
            ->groupBy('wallet_id')
            ->map(fn ($balances) => $balances->sortByDesc('start_date')->first());
        $thirdParty = $latest->filter(fn ($balance) => $balance->wallet?->is_third_party);

        $current = $monthlySummary[$month] ?? [];
        $before = $monthlySummary[$previous] ?? [];
        $income = (float) ($current['total_income'] ?? 0);
        $expenses = (float) ($current['total_expenses'] ?? 0);

        // held amounts (reserved_amount) are a snapshot of today, as on the balances screen
        $isLatestMonth = $month >= max(array_keys($monthlySummary) ?: [$month]);
        $reserved = $isLatestMonth ? (float) $latest->sum(fn ($balance) => $balance->wallet?->reserved_amount ?? 0) : 0;

        return [
            'net_worth' => (float) $latest->sum('calculated_balance') + $reserved,
            'in_banks' => (float) $latest->diffKeys($thirdParty)->sum('calculated_balance') + $reserved,
            'third_party' => -(float) $thirdParty->sum('calculated_balance'),
            'net_worth_change' => (float) ($current['balance_change'] ?? 0),
            'income' => $income,
            'expenses' => $expenses,
            'result' => $income + $expenses,
            'previous_income' => (float) ($before['total_income'] ?? 0),
            'previous_expenses' => (float) ($before['total_expenses'] ?? 0),
            'previous_result' => (float) (($before['total_income'] ?? 0) + ($before['total_expenses'] ?? 0)),
            'has_previous' => $before !== [],
        ];
    }

    /**
     * Net worth at the end of each month ("Y-m"): latest balance of every wallet up to it.
     */
    public static function netWorthSeries(array $months): array
    {
        $balances = FinancialBalance::orderBy('start_date')->get(['wallet_id', 'start_date', 'calculated_balance']);
        $series = [];
        foreach ($months as $month) {
            $end = Carbon::createFromFormat('Y-m-d', $month . '-01')->endOfMonth()->toDateString();
            $series[$month] = round($balances->where('start_date', '<=', $end)
                ->groupBy('wallet_id')
                ->sum(fn ($walletBalances) => (float) $walletBalances->last()->calculated_balance), 2);
        }
        return $series;
    }
}
