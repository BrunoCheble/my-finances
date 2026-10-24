<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\FinancialBalanceSummaryService;
use App\Services\FinancialCategorySummaryService;
use App\Services\Alerts\AlertService;
use App\Services\Cache\DashboardCacheService;
use App\Services\CategoryMonthlyService;
use App\Services\DashboardKpiService;
use DateTime;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $month = $request->input('month') ?? date('Y-m');

        $data = DashboardCacheService::get($month, function () use ($month) {
            $monthlySummary = FinancialBalanceSummaryService::getMonthlySummary();
            $categorySummary = FinancialCategorySummaryService::execute();
            $alerts = AlertService::for($month);

            return compact('monthlySummary', 'categorySummary', 'alerts');
        });

        $alerts = $data['alerts'] ?? [];

        $filter = collect($data['monthlySummary'])->mapWithKeys(fn($value, $key) => [
            $key => \DateTime::createFromFormat('Y-m', $key)->format('m/Y')
        ])->all();

        $range = in_array((int) $request->input('range'), CategoryMonthlyService::RANGES, true) ? (int) $request->input('range') : 6;

        return view('dashboard', [
            'month' => $month,
            'range' => $range,
            'categoryTable' => CategoryMonthlyService::build($month, $range),
            'kpis' => DashboardKpiService::for($month, $data['monthlySummary']),
            'monthlySummary' => $data['monthlySummary'],
            'categorySummary' => $data['categorySummary'],
            'alerts' => $alerts,
            'filter' => $filter,
        ]);
    }
}
