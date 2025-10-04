<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\Alerts\CategoryImbalanceAlert;
use App\Services\Alerts\CustomMovementAlert;
use App\Services\FinancialBalanceSummaryService;
use App\Services\FinancialCategorySummaryService;
use App\Services\Alerts\SpendingSpikeAlert;
use App\Services\Cache\DashboardCacheService;
use DateTime;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $month = $request->input('month') ?? date('Y-m');
        DashboardCacheService::clearMonth($month); // Limpa cache();
        $data = DashboardCacheService::get($month, function () use ($month) {
            $monthlySummary = FinancialBalanceSummaryService::getMonthlySummary();
            $categorySummary = FinancialCategorySummaryService::execute();
            $spendingSpikeAlert = SpendingSpikeAlert::check($month);
            $categoryImbalanceAlert = CategoryImbalanceAlert::check($month);
            $customAlert = CustomMovementAlert::check($month);

            return compact(
                'monthlySummary',
                'categorySummary',
                'spendingSpikeAlert',
                'categoryImbalanceAlert',
                'customAlert'
            );
        });

        $alerts = [
            'spending_spike_alert' => isset($data['spendingSpikeAlert']) ? $data['spendingSpikeAlert'] : null,
            'category_imbalance_alert' => isset($data['categoryImbalanceAlert']) ? $data['categoryImbalanceAlert'] : null,
            'custom_alert' => isset($data['customAlert']) ? $data['customAlert'] : null,
        ];

        $filter = collect($data['monthlySummary'])->mapWithKeys(fn($value, $key) => [
            $key => \DateTime::createFromFormat('Y-m', $key)->format('m/Y')
        ])->all();

        return view('dashboard', [
            'monthlySummary' => $data['monthlySummary'],
            'categorySummary' => $data['categorySummary'],
            'alerts' => $alerts,
            'filter' => $filter,
        ]);
    }
}
