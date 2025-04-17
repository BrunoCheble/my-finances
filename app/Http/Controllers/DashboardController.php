<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\FinancialBalanceSummaryService;
use App\Services\FinancialCategorySummaryService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $monthlySummary = FinancialBalanceSummaryService::getMonthlySummary();
        $categorySummary = FinancialCategorySummaryService::execute();

        return view('dashboard', compact('monthlySummary', 'categorySummary'));
    }
}
