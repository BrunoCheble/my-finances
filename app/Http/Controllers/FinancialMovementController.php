<?php

namespace App\Http\Controllers;

use App\Enums\FinancialMovementOptions;
use App\Enums\FinancialMovementType;
use App\Helpers\ArrayHelper;
use App\Http\Requests\FinancialMovementBulkDeleteRequest;
use App\Http\Requests\FinancialMovementRequest;
use App\Models\FinancialBalance;
use App\Models\FinancialCategory;
use App\Models\FinancialMovement;
use App\Models\Wallet;
use App\Services\BulkDeleteFinancialMovementService;
use App\Services\Cache\DashboardCacheService;
use App\Services\CalculateFinancialBalanceService;
use App\Services\CategoryOptionsService;
use App\Services\DescriptionSuggestionsService;
use App\Services\LoanService;
use App\Services\ReconciliationService;
use App\Services\GetFinancialMovementService;
use App\Services\SaveFinancialMovementService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;

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
        $categoryOptions = CategoryOptionsService::forPicker();
        $openLoans = LoanService::openPeople();
        $reconciledMonths = ReconciliationService::lastReconciledMonths();

        // inactive wallets stay at the end of the switcher, for their history
        $walletsSidebar = Wallet::orderByDesc('active')->orderBy('name')->get();
        $wallets = ArrayHelper::toKeyValueArray($walletsSidebar, 'id', 'name');
        $walletSection = Wallet::find($request->input('wallet_id'));

        $availableAttributes = FinancialMovementOptions::options();
        $totalBalance = $request->input('wallet_id') ? FinancialBalance::where('wallet_id', $request->input('wallet_id'))
                            ->orderBy('end_date', 'desc')
                            ->first()?->calculated_balance ?? null : null;

        return view('financial-movements.index', compact(
            'financials',
            'walletSection',
            'wallets',
            'walletsSidebar',
            'categories',
            'types',
            'availableAttributes',
            'financialMovement',
            'totalBalance',
            'categoryOptions',
            'openLoans',
            'reconciledMonths'
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
            $balances = [];
            foreach ($movements as $movement) {
                $balance = $calculateFinancialBalanceService->execute($movement->date, $movement->wallet_id);
                if ($balance) {
                    $balances[] = $balance;
                }
            }
            $dashboardCacheService->clearAllByUser();
            return response()->json(['balances' => $balances, 'movements' => $movements], Response::HTTP_CREATED);
        } catch (ValidationException $e) {
            // e.g. a repayment from someone without an open loan: show the reason
            throw $e;
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
            // the wallet/date before the edit must be recalculated too
            $affected = $this->affectedPositions($financial_movement);
            $movements = $saveFinancialMovementService->execute($request->validated(), $financial_movement->id);
            $calculateFinancialBalanceService->executeForMovements(
                $affected->merge($movements->map(fn ($m) => $m->only('wallet_id', 'date')))
            );
            $dashboardCacheService->clearAllByUser();
            return Redirect::route('financial-movements.index')
                ->with('success', 'Financial Movement updated successfully');
        } catch (\Exception $e) {
            return Redirect::route('financial-movements.index')
                ->with('error', __('Something went wrong'));
        }
    }

    public function destroy(
        Request $request,
        FinancialMovement $financial_movement,
        DashboardCacheService $dashboardCacheService,
        CalculateFinancialBalanceService $calculateFinancialBalanceService
    ): RedirectResponse
    {
        $this->guardConfirmedMonths($request, collect([$financial_movement]));
        try {
            $affected = $this->affectedPositions($financial_movement);
            $financial_movement->delete();
            $calculateFinancialBalanceService->executeForMovements($affected);
            $dashboardCacheService->clearAllByUser();

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
        $financial_movement = FinancialMovement::findOrFail($request->id);
        $this->guardConfirmedMonths($request, collect([$financial_movement]));
        try {
            $affected = $this->affectedPositions($financial_movement);
            $financial_movement->delete();

            $calculateFinancialBalanceService->executeForMovements($affected);
            $dashboardCacheService->clearAllByUser();
            return response()->json(['success' => 'Financial Movement deleted successfully'], Response::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json(['error' => __('Something went wrong')], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function bulkDelete(
        FinancialMovementBulkDeleteRequest $request,
        BulkDeleteFinancialMovementService $bulkDeleteFinancialMovementService,
        DashboardCacheService $dashboardCacheService,
        CalculateFinancialBalanceService $calculateFinancialBalanceService
    ): JsonResponse
    {
        $ids = $request->input('ids', []);
        $this->guardConfirmedMonths($request, FinancialMovement::whereIn('id', $ids)->get());
        try {

            $movements = $bulkDeleteFinancialMovementService->execute($ids);
            $calculateFinancialBalanceService->executeForMovements($movements);
            $dashboardCacheService->clearAllByUser();
            return response()->json(['success' => 'Selected Financial Movements deleted successfully'], Response::HTTP_OK);
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

    /**
     * Deleting from a confirmed month needs an explicit confirmation (confirm_reconciled).
     */
    private function guardConfirmedMonths(Request $request, $movements): void
    {
        if ($request->boolean('confirm_reconciled')) {
            return;
        }
        $positions = $movements->flatMap(fn ($movement) => $this->affectedPositions($movement));
        $balance = FinancialMovementRequest::confirmedBalanceAffected($positions);
        if ($balance) {
            throw ValidationException::withMessages(['confirm_reconciled' => __('This changes the confirmed balance of :wallet in :month. Confirm to continue.', [
                'wallet' => $balance->wallet?->name,
                'month' => ucfirst(\Carbon\Carbon::parse($balance->start_date)->translatedFormat('F Y')),
            ])]);
        }
    }

    /**
     * Wallet/date of the movement and of its transfer mirror (deleted along with it).
     */
    private function affectedPositions(FinancialMovement $movement)
    {
        return collect([$movement, $movement->destinationMovement])
            ->filter()
            ->map(fn ($m) => $m->only('wallet_id', 'date'))
            ->values();
    }

    public function suggestions(Request $request): JsonResponse
    {
        return response()->json(DescriptionSuggestionsService::search((string) $request->input('q')));
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
