<?php

namespace App\Services;

use App\Models\FinancialBalance;

class FinancialBalanceAccordionService
{
    public static function execute()
    {
        $balances = FinancialBalance::with('wallet')->get()
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
}
