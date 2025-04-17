<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Helpers\ArrayHelper;
use App\Models\FinancialBalance;
use App\Http\Requests\FinancialBalanceRequest;
use App\Models\Wallet;
use App\Services\CalculateFinancialBalanceService;
use App\Services\FinancialBalanceAccordionService;
use Illuminate\Contracts\View\View;

class FinancialBalanceController extends Controller
{
    public function index(Request $request): View
    {
        $startDate = $request->input('start_date') ?? date('Y-m-01');
        $endDate = $request->input('end_date') ?? date('Y-m-t');

        $groups = FinancialBalanceAccordionService::execute();
        $wallets = ArrayHelper::toKeyValueArray(Wallet::all(), 'id', 'name');
        return view('financial-balances.index', compact('groups','wallets', 'startDate', 'endDate'));
    }

    public function create(): View
    {
        $balance = new FinancialBalance();
        $wallets = ArrayHelper::toKeyValueArray(Wallet::all(), 'id', 'name');
        return view('financial-balances.create', compact('balance','wallets'));
    }

    public function store(FinancialBalanceRequest $request)
    {
        FinancialBalance::create($request->validated());
        return redirect()->route('financial-balances.index')->with('success', 'Financial Balance created successfully.');
    }

    public function show(FinancialBalance $financial_balance): View
    {
        return view('financial-balances.show', compact('financialBalance'));
    }

    public function edit($id): View
    {
        $balance = FinancialBalance::find($id);
        $wallets = ArrayHelper::toKeyValueArray(Wallet::all(), 'id', 'name');
        return view('financial-balances.edit', compact('balance','wallets'));
    }

    public function update(FinancialBalanceRequest $request, FinancialBalance $financial_balance)
    {
        $financial_balance->update($request->validated());
        return redirect()->route('financial-balances.index')->with('success', 'Financial Balance updated successfully.');
    }

    public function destroy(FinancialBalance $financial_balance)
    {
        $financial_balance->delete();
        return redirect()->route('financial-balances.index')->with('success', 'Financial Balance deleted successfully.');
    }

    public function recalculate(int $id)
    {
        $financial_balance = FinancialBalance::find($id);
        CalculateFinancialBalanceService::execute($financial_balance->start_date, $financial_balance->wallet_id);
        return redirect()->route('financial-balances.index')->with('success', 'Financial Balance recalculated successfully.');
    }

    public function recalculateAll(string $startDate, string $endDate)
    {
        $financial_balances = FinancialBalance::where('start_date', $startDate)->where('end_date', $endDate)->get();
        foreach ($financial_balances as $financial_balance) {
            CalculateFinancialBalanceService::execute($financial_balance->start_date, $financial_balance->wallet_id);
        }
        return redirect()->route('financial-balances.index', ['start_date' => $startDate, 'end_date' => $endDate])->with('success', 'Financial Balance recalculated successfully.');
    }
}
