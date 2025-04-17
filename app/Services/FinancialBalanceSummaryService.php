<?php

namespace App\Services;

use App\Models\FinancialBalance;
use Illuminate\Support\Facades\DB;

class FinancialBalanceSummaryService
{
    public static function getMonthlySummary()
    {
        return FinancialBalance::select(
            DB::raw("DATE_FORMAT(start_date, '%Y-%m') as month"),
            DB::raw("SUM(calculated_balance) as total_balance"),
            DB::raw("SUM(calculated_balance-initial_balance) as balance_change"),
            DB::raw("SUM(total_expense) as total_expenses"),
            DB::raw("SUM(total_income) as total_income"),
            DB::raw("SUM(total_unidentified) as total_unidentified")
        )
        ->groupBy(DB::raw("DATE_FORMAT(start_date, '%Y-%m')"))
        ->orderBy('month')
        ->get()
        ->keyBy('month')
        ->toArray();
    }
}
