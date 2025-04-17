<?php

namespace App\Http\Controllers;

use App\Enums\FinancialMovementOptions;
use App\Enums\FinancialMovementType;
use App\Helpers\ArrayHelper;
use App\Http\Requests\FinancialMovementRequest;
use App\Models\FinancialCategory;
use App\Models\FinancialMovement;
use App\Models\Wallet;
use App\Services\CalculateFinancialBalanceService;
use App\Services\GetFinancialMovementService;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;

class FinancialMovementController extends Controller
{
    public function index(Request $request): View
    {
        $financials = GetFinancialMovementService::execute(
            $request->input('attribute'),
            $request->input('search'),
            $request->input('sort'),
            $request->input('order'),
            $request->input('date_from'),
            $request->input('date_to'),
            $request->input('wallet_id')
        );

        $financialMovement = new FinancialMovement();
        $types = FinancialMovementType::options();
        $categories = ArrayHelper::toKeyValueArray(FinancialCategory::all(), 'id', 'name');

        $walletsSidebar = Wallet::all();
        $wallets = ArrayHelper::toKeyValueArray($walletsSidebar, 'id', 'name');
        $walletSection = Wallet::find($request->input('wallet_id'));

        $availableAttributes = FinancialMovementOptions::options();

        return view('financial-movements.index', compact(
            'financials',
            'walletSection',
            'wallets',
            'walletsSidebar',
            'categories',
            'types',
            'availableAttributes',
            'financialMovement'
        ))->with('i', ($request->input('page', 1) - 1) * 5);
    }

    public function store(FinancialMovementRequest $request): RedirectResponse
    {
        try {
            FinancialMovement::create($request->validated());
            CalculateFinancialBalanceService::execute($request->date,$request->wallet_id);

            return Redirect::route('financial-movements.index')
                ->with('success', 'Financial Movement created successfully');
        } catch (\Exception $e) {
            return Redirect::route('financial-movements.index')
                ->with('error', __('Something went wrong'));
        }
    }

    public function update(FinancialMovementRequest $request, FinancialMovement $financial_movement): RedirectResponse
    {
        try {
            $financial_movement->update($request->validated());
            CalculateFinancialBalanceService::execute($request->date,$request->wallet_id);
            return Redirect::route('financial-movements.index')
                ->with('success', 'Financial Movement updated successfully');
        } catch (\Exception $e) {
            return Redirect::route('financial-movements.index')
                ->with('error', __('Something went wrong'));
        }
    }

    public function destroy(FinancialMovement $financial_movement)
    {
        try {
            $financial_movement->delete();
            CalculateFinancialBalanceService::execute($financial_movement->date, $financial_movement->wallet_id);
            return Redirect::route('financial-movements.index')
                ->with('success', 'Financial Movement deleted successfully');
        } catch (\Exception $e) {
            return Redirect::route('financial-movements.index')
                ->with('error', __('Something went wrong'));
        }
    }
}
