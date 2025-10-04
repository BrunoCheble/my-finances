<?php

namespace App\Http\Controllers;

use App\Enums\FinancialMovementOptions;
use App\Enums\FinancialMovementType;
use App\Helpers\ArrayHelper;
use App\Http\Requests\FinancialMovementRequest;
use App\Models\FinancialCategory;
use App\Models\FinancialMovement;
use App\Models\Wallet;
use App\Services\Cache\DashboardCacheService;
use App\Services\CalculateFinancialBalanceService;
use App\Services\FinancialMovementSummaryService;
use App\Services\GetFinancialMovementService;
use App\Services\SaveFinancialMovementService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
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


    public function store(
        FinancialMovementRequest $request,
        SaveFinancialMovementService $saveFinancialMovementService,
        DashboardCacheService $dashboardCacheService,
        CalculateFinancialBalanceService $calculateFinancialBalanceService
    ): JsonResponse
    {
        try {
            $movements = $saveFinancialMovementService->execute($request->validated());
            foreach ($movements as $movement) {
                $calculateFinancialBalanceService->execute($movement->date, $movement->wallet_id);
                $dashboardCacheService->clearRelatedCaches($movement->date);
            }
            return response()->json($movements, Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return response()->json(['error' => __('Something went wrong')], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function update(
        FinancialMovementRequest $request,
        FinancialMovement $financial_movement,
        SaveFinancialMovementService $saveFinancialMovementService,
        DashboardCacheService $dashboardCacheService,
        CalculateFinancialBalanceService $calculateFinancialBalanceService
    ): RedirectResponse
    {
        try {
            $movements = $saveFinancialMovementService->execute($request->validated(), $financial_movement->id);
            foreach ($movements as $movement) {
                $calculateFinancialBalanceService->execute($movement->date, $movement->wallet_id);
                $dashboardCacheService->clearRelatedCaches($movement->date);
            }
            return Redirect::route('financial-movements.index')
                ->with('success', 'Financial Movement updated successfully');
        } catch (\Exception $e) {
            return Redirect::route('financial-movements.index')
                ->with('error', __('Something went wrong'));
        }
    }

    public function destroy(
        FinancialMovement $financial_movement,
        DashboardCacheService $dashboardCacheService,
        CalculateFinancialBalanceService $calculateFinancialBalanceService
    ): RedirectResponse
    {
        try {
            $financial_movement->delete();
            $calculateFinancialBalanceService->execute($financial_movement->date, $financial_movement->wallet_id);
            $dashboardCacheService->clearRelatedCaches($financial_movement->date);

            return Redirect::route('financial-movements.index')
                ->with('success', 'Financial Movement deleted successfully');
        } catch (\Exception $e) {
            return Redirect::route('financial-movements.index')
                ->with('error', __('Something went wrong'));
        }
    }

    public function delete(
        Request $request,
        DashboardCacheService $dashboardCacheService,
        CalculateFinancialBalanceService $calculateFinancialBalanceService
    ): JsonResponse
    {
        try {
            $financial_movement = FinancialMovement::find($request->id);
            $financial_movement->delete();

            $calculateFinancialBalanceService->execute($financial_movement->date, $financial_movement->wallet_id);
            $dashboardCacheService->clearRelatedCaches($financial_movement->date);
            return response()->json(['success' => 'Financial Movement deleted successfully'], Response::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json(['error' => __('Something went wrong')], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    public function filter(Request $request): JsonResponse
    {
        $financials = GetFinancialMovementService::execute(
            $request->input('attribute'),
            $request->input('search'),
            $request->input('sort'),
            $request->input('order'),
            $request->input('date_from'),
            $request->input('date_to'),
            $request->input('wallet_id'),
            false
        );

        return response()->json($financials);
    }

    public function fetchLatestTypeAndCategory(Request $request): JsonResponse
    {
        $movements = GetFinancialMovementService::execute(
            FinancialMovementOptions::DESCRIPTION,
            $request->input('search'),
            null,
            null,
            null,
            null,
            null,
            false,
            1
        );

        return response()->json($movements[0] ?? []);
    }
}
