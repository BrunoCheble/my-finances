<?php

namespace App\Services;

use App\Models\FinancialBalance;

class FinancialBalanceAccordionService
{
    public static function execute($year = null)
    {
        $balances = FinancialBalance::with('wallet')
            ->when($year, fn($query) => $query->whereYear('start_date', $year))
            ->get()
            ->sortBy('wallet.name')
            ->sortBy('start_date', SORT_REGULAR, true);

        $groupedBalances = $balances->groupBy(function ($balance) {
            return date_format_custom($balance->start_date) . ' - ' . date_format_custom($balance->end_date);
        })->map(function ($items, $key) {
            return (object) [
                'title'      => $key,
                'startDate' => $items->first()->start_date,
                'endDate'   => $items->first()->end_date,
                'balances'   => $items,
            ];
        });

        $result = $groupedBalances->values();

        if ($result->isEmpty()) {
            return $result;
        }

        $result[0]->balances
            ->filter(fn ($b) => $b->wallet->reserved_amount != 0)
            ->each(function ($balance) {
                $reserved = $balance->wallet->reserved_amount;
                $balance->calculated_balance += $reserved;
                $balance->reserved_amount = $reserved;
            });

        return $result;
    }

    public static function getYears()
    {
        return FinancialBalance::selectRaw('DISTINCT YEAR(start_date) as year')
            ->orderBy('year', 'desc')
            ->pluck('year');
    }

    public static function getSummary($groups)
    {
        if ($groups->isEmpty()) {
            return ['net_worth' => 0, 'in_banks' => 0, 'third_party' => 0, 'total_unidentified' => 0, 'total_calculated' => 0, 'total_initial' => 0];
        }

        $totalUnidentified = $groups->flatMap(fn($group) => $group->balances)->sum('total_unidentified');
        $totalCalculated = $groups->first()->balances->sum('calculated_balance');
        $totalInitial = $groups->last()->balances->sum('initial_balance');

        // latest balance of each wallet (a wallet may have no movement in the newest month)
        $latest = $groups->flatMap(fn ($group) => $group->balances)
            ->groupBy('wallet_id')
            ->map(fn ($balances) => $balances->sortByDesc('start_date')->first());
        $thirdParty = $latest->filter(fn ($balance) => $balance->wallet->is_third_party);

        return [
            'net_worth' => $latest->sum('calculated_balance'),
            'in_banks' => $latest->reject(fn ($balance) => $balance->wallet->is_third_party)->sum('calculated_balance'),
            'third_party' => -$thirdParty->sum('calculated_balance'),
            'total_unidentified' => $totalUnidentified,
            'total_calculated' => $totalCalculated,
            'total_initial' => $totalInitial,
        ];
    }
}
