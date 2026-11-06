<?php

namespace App\Http\Controllers;

use App\Enums\FinancialMovementType;
use Illuminate\Http\Request;
use App\Helpers\ArrayHelper;
use App\Models\FinancialBalance;
use App\Models\FinancialMovement;
use App\Http\Requests\FinancialBalanceRequest;
use App\Models\Wallet;
use App\Services\AdjustFinancialBalanceService;
use App\Services\Cache\DashboardCacheService;
use App\Services\CalculateFinancialBalanceService;
use App\Services\FinancialBalanceAccordionService;
use App\Services\NetWorthHistoryService;
use App\Services\OpenMonthsService;
use App\Services\ReconciliationService;
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

    public function reconcile(FinancialBalance $financial_balance, ReconciliationService $reconciliation)
    {
        $reconciliation->reconcile($financial_balance);
        return $this->backToMonth($financial_balance->start_date, __(':wallet confirmed for :month.', [
            'wallet' => $financial_balance->wallet_name,
            'month' => $this->monthName($financial_balance->start_date),
        ]));
    }

    /**
     * Confirms every wallet of the month that isn't confirmed yet (or diverged).
     */
    public function reconcileMonth(string $startDate, ReconciliationService $reconciliation)
    {
        abort_unless(preg_match('/^\d{4}-\d{2}-01$/', $startDate), 404);
        $balances = FinancialBalance::where('start_date', $startDate)->get();
        foreach ($balances as $balance) {
            $reconciliation->reconcile($balance);
        }
        return $this->backToMonth($startDate, __(':month confirmed.', ['month' => $this->monthName($startDate)]));
    }

    public function unreconcile(FinancialBalance $financial_balance, ReconciliationService $reconciliation)
    {
        $reconciliation->unreconcile($financial_balance);
        return $this->backToMonth($financial_balance->start_date, __('Confirmation removed.'));
    }

    /**
     * Creates an adjustment that brings the month back to the confirmed balance.
     */
    public function restore(FinancialBalance $financial_balance, ReconciliationService $reconciliation, AdjustFinancialBalanceService $adjust)
    {
        $reconciliation->restore($financial_balance, $adjust);
        DashboardCacheService::clearAllByUser();
        return $this->backToMonth($financial_balance->start_date, __('Adjustment created: the balance is back to the confirmed value.'));
    }

    /**
     * Removes a balance created by mistake: only when the wallet has no movements in its period.
     */
    public function destroy(FinancialBalance $financial_balance)
    {
        $hasMovements = FinancialMovement::where('wallet_id', $financial_balance->wallet_id)
            ->whereBetween('date', [$financial_balance->start_date, $financial_balance->end_date])
            ->exists();
        if ($hasMovements) {
            return $this->backToMonth($financial_balance->start_date, __('This balance has movements and cannot be removed.'), 'error');
        }

        $financial_balance->delete();
        return $this->backToMonth($financial_balance->start_date, __('Balance removed.'));
    }

    /**
     * Changes the period of a balance; it can't overlap another balance of the same wallet.
     */
    public function updateDates(Request $request, FinancialBalance $financial_balance, CalculateFinancialBalanceService $calculateFinancialBalanceService)
    {
        $bag = 'dates' . $financial_balance->id;
        $data = $request->validateWithBag($bag, [
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        $overlaps = FinancialBalance::where('wallet_id', $financial_balance->wallet_id)
            ->where('id', '!=', $financial_balance->id)
            ->where('start_date', '<=', $data['end_date'])
            ->where('end_date', '>=', $data['start_date'])
            ->exists();
        if ($overlaps) {
            return back()->withErrors(['start_date' => __('These dates overlap another balance of this wallet.')], $bag)->withInput();
        }

        $financial_balance->update($data);
        $calculateFinancialBalanceService->recalculateFrom($financial_balance->wallet_id, $financial_balance->start_date);

        return $this->backToMonth($financial_balance->start_date, __('Dates updated.'));
    }

    /**
     * Adds a wallet to a month it isn't in: it is calculated like the others (balance carried
     * from before plus the month's movements). With nothing carried and no movements there is
     * nothing to show, and no balance is created.
     */
    public function addWallet(Request $request, string $startDate, CalculateFinancialBalanceService $calculateFinancialBalanceService)
    {
        abort_unless(preg_match('/^\d{4}-\d{2}-01$/', $startDate), 404);
        $walletId = (int) $request->validate(['wallet_id' => ['required', 'integer', 'exists:wallets,id']])['wallet_id'];
        $wallet = Wallet::findOrFail($walletId);
        $month = $this->monthName($startDate);

        if (FinancialBalance::where('wallet_id', $walletId)->where('start_date', '<=', $startDate)->where('end_date', '>=', $startDate)->exists()) {
            return $this->backToMonth($startDate, __(':wallet is already in :month.', ['wallet' => $wallet->name, 'month' => $month]), 'error');
        }

        $calculateFinancialBalanceService->recalculateFrom($walletId, $startDate);

        if (!FinancialBalance::where('wallet_id', $walletId)->where('start_date', $startDate)->exists()) {
            return $this->backToMonth($startDate, __(':wallet has no balance nor movements in :month.', ['wallet' => $wallet->name, 'month' => $month]), 'error');
        }
        return $this->backToMonth($startDate, __(':wallet added to :month.', ['wallet' => $wallet->name, 'month' => $month]));
    }

    private function backToMonth(string $startDate, string $message, string $type = 'success')
    {
        DashboardCacheService::clearAllByUser();
        return redirect()->route('financial-balances.index', ['year' => substr($startDate, 0, 4)])
            ->withFragment('month-' . substr($startDate, 0, 7))
            ->with($type, $message);
    }

    private function monthName(string $date): string
    {
        return ucfirst(\Carbon\Carbon::parse($date)->translatedFormat('F Y'));
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
        return redirect()->route('financial-balances.index')->with('success', __('Balance adjusted.'));
    }


    public function recalculate(int $id, CalculateFinancialBalanceService $calculateFinancialBalanceService)
    {
        $financial_balance = FinancialBalance::findOrFail($id);
        $calculateFinancialBalanceService->execute($financial_balance->start_date, $financial_balance->wallet_id);
        DashboardCacheService::clearAllByUser();
        return redirect()->route('financial-balances.index')->with('success', __('Balance recalculated.'));
    }

    public function recalculateAll(string $startDate, string $endDate, CalculateFinancialBalanceService $calculateFinancialBalanceService)
    {
        $financial_balances = FinancialBalance::where('start_date', $startDate)->where('end_date', $endDate)->get();
        foreach ($financial_balances as $financial_balance) {
            $calculateFinancialBalanceService->execute($financial_balance->start_date, $financial_balance->wallet_id);
        }
        DashboardCacheService::clearAllByUser();
        return redirect()->route('financial-balances.index', ['start_date' => $startDate, 'end_date' => $endDate])->with('success', __('Balance recalculated.'));
    }
}
