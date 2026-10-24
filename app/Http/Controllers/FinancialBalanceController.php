<?php

namespace App\Http\Controllers;

use App\Enums\FinancialMovementType;
use Illuminate\Http\Request;
use App\Helpers\ArrayHelper;
use App\Models\FinancialBalance;
use App\Http\Requests\FinancialBalanceRequest;
use App\Models\Wallet;
use App\Services\AdjustFinancialBalanceService;
use App\Services\Cache\DashboardCacheService;
use App\Services\CalculateFinancialBalanceService;
use App\Services\FinancialBalanceAccordionService;
use App\Services\NetWorthHistoryService;
use App\Services\OpenMonthsService;
use Illuminate\Contracts\View\View;

class FinancialBalanceController extends Controller
{
    public function index(Request $request, OpenMonthsService $openMonthsService): View
    {
        // every active wallet shows up in every month, with its balance carried forward
        if ($openMonthsService->execute() > 0) {
            DashboardCacheService::clearAllByUser();
        }

        $startDate = $request->input('start_date') ?? date('Y-m-01');
        $endDate = $request->input('end_date') ?? date('Y-m-t');
        $year = $request->input('year') ?? null;

        $groups = FinancialBalanceAccordionService::execute($year);
        $summary = FinancialBalanceAccordionService::getSummary($groups);
        $wallets = ArrayHelper::toKeyValueArray(Wallet::all(), 'id', 'name');
        $types = FinancialMovementType::options();

        $filter = FinancialBalanceAccordionService::getYears()->mapWithKeys(fn($value) => [
            $value => $value
        ])->all();

        $period = array_key_exists($request->input('period'), NetWorthHistoryService::PERIODS) ? $request->input('period') : '1y';
        $history = NetWorthHistoryService::build(NetWorthHistoryService::PERIODS[$period]);

        return view('financial-balances.index', compact('groups', 'summary', 'wallets', 'types', 'startDate', 'endDate', 'filter', 'period', 'history'));
    }




    public function edit($id): View
    {
        $balance = FinancialBalance::find($id);
        $wallets = ArrayHelper::toKeyValueArray(Wallet::all(), 'id', 'name');
        return view('financial-balances.edit', compact('balance','wallets'));
    }

    public function update(FinancialBalanceRequest $request, FinancialBalance $financial_balance, AdjustFinancialBalanceService $adjustFinancialBalanceService)
    {
        $adjustFinancialBalanceService->adjustToRealBalance(
            $financial_balance->wallet_id,
            $financial_balance->start_date,
            (float) $request->validated()['real_balance']
        );
        DashboardCacheService::clearAllByUser();
        return redirect()->route('financial-balances.index')->with('success', 'Financial Balance updated successfully.');
    }


    public function recalculate(int $id, CalculateFinancialBalanceService $calculateFinancialBalanceService)
    {
        $financial_balance = FinancialBalance::findOrFail($id);
        $calculateFinancialBalanceService->execute($financial_balance->start_date, $financial_balance->wallet_id);
        DashboardCacheService::clearAllByUser();
        return redirect()->route('financial-balances.index')->with('success', 'Financial Balance recalculated successfully.');
    }

    public function recalculateAll(string $startDate, string $endDate, CalculateFinancialBalanceService $calculateFinancialBalanceService)
    {
        $financial_balances = FinancialBalance::where('start_date', $startDate)->where('end_date', $endDate)->get();
        foreach ($financial_balances as $financial_balance) {
            $calculateFinancialBalanceService->execute($financial_balance->start_date, $financial_balance->wallet_id);
        }
        DashboardCacheService::clearAllByUser();
        return redirect()->route('financial-balances.index', ['start_date' => $startDate, 'end_date' => $endDate])->with('success', 'Financial Balance recalculated successfully.');
    }
}
