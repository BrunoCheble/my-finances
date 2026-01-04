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

        return $groupedBalances->values();
    }

    public static function getSummary($groups)
    {
        $totalUnidentified = $groups->flatMap(fn($group) => $group->balances)->sum('total_unidentified');
        $totalCalculated = $groups->first()->balances->sum('calculated_balance');
        $totalInitial = $groups->last()->balances->sum('initial_balance');

        return [
            'total_unidentified' => $totalUnidentified,
            'total_calculated' => $totalCalculated,
            'total_initial' => $totalInitial,
        ];
    }
}
