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
            return $balance->start_date_formatted . ' - ' . $balance->end_date_formatted;
        })->map(function ($items, $key) {
            return (object) [
                'title'      => $key,
                'startDate' => $items->first()->start_date,
                'endDate'   => $items->first()->end_date,
                'balances'   => $items,
                'completed'  => $items->some(fn($balance) => $balance->completed),
            ];
        });

        return $groupedBalances->values();
    }
}
