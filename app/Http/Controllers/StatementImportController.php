<?php

namespace App\Http\Controllers;

use App\Enums\FinancialMovementType;
use App\Helpers\ArrayHelper;
use App\Models\FinancialCategory;
use App\Models\FinancialMovement;
use App\Models\StatementImport;
use App\Models\Wallet;
use App\Repositories\Interfaces\FinancialBalanceRepositoryInterface;
use App\Services\Cache\DashboardCacheService;
use App\Services\Statements\ConfirmStatementImportService;
use App\Services\Statements\CreateStatementImportService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class StatementImportController extends Controller
{
    public function index(): View
    {
        $imports = StatementImport::with('wallet')->withCount('lines')->latest()->get();
        // third-party money has no bank statement of its own
        $wallets = ArrayHelper::toKeyValueArray(Wallet::own()->get(), 'id', 'name');

        return view('statement-imports.index', compact('imports', 'wallets'));
    }

    public function store(Request $request, CreateStatementImportService $createStatementImportService): RedirectResponse
    {
        $data = $request->validate([
            'file' => 'required|file|mimes:pdf|max:10240',
            'wallet_id' => ['required', Rule::exists('wallets', 'id')->where('user_id', auth()->id())->whereNot('type', Wallet::TYPE_THIRD_PARTY)],
        ]);

        try {
            // the PDF is only read here; it is never stored
            $import = $createStatementImportService->execute(
                $data['file']->getRealPath(),
                $data['file']->getClientOriginalName(),
                (int) $data['wallet_id']
            );
        } catch (InvalidArgumentException $e) {
            return redirect()->route('statement-imports.index')->with('error', $e->getMessage());
        }

        return redirect()->route('statement-imports.show', $import);
    }

    /**
     * Called when a file is chosen: recognizes bank/account and the remembered wallet.
     */
    public function identify(Request $request, CreateStatementImportService $createStatementImportService): JsonResponse
    {
        $data = $request->validate(['file' => 'required|file|mimes:pdf|max:10240']);

        try {
            return response()->json($createStatementImportService->identify($data['file']->getRealPath()));
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function show(StatementImport $statementImport, FinancialBalanceRepositoryInterface $balanceRepository): View
    {
        $statementImport->load('lines.duplicateMovement', 'wallet');

        $lastDate = $statementImport->period_end ?? $statementImport->lines->max('date');
        $balance = $balanceRepository->findByWalletAndInterval($statementImport->wallet_id, $lastDate)
            ?? $balanceRepository->findPreviousByWalletAndDate($statementImport->wallet_id, $lastDate);
        $systemBalance = (float) ($balance?->calculated_balance ?? 0);

        $types = FinancialMovementType::options();
        $uses = FinancialMovement::whereNotNull('category_id')
            ->selectRaw('category_id, count(*) as total')
            ->groupBy('category_id')
            ->pluck('total', 'category_id');
        $categoriesData = FinancialCategory::active()->orderBy('name')->get(['id', 'name', 'type'])
            ->map(fn ($category) => [
                'id' => (string) $category->id,
                'name' => $category->name,
                'type' => $category->type,
                'uses' => (int) ($uses[$category->id] ?? 0),
            ])->values();
        $wallets = ArrayHelper::toKeyValueArray(Wallet::orderBy('name')->get(), 'id', 'name');
        $categorizedTypes = ConfirmStatementImportService::CATEGORIZED_TYPES;

        return view('statement-imports.show', compact(
            'statementImport',
            'systemBalance',
            'types',
            'categoriesData',
            'wallets',
            'categorizedTypes'
        ));
    }

    public function update(Request $request, StatementImport $statementImport, ConfirmStatementImportService $confirmStatementImportService): RedirectResponse
    {
        $created = $confirmStatementImportService->execute($statementImport, $request->input('lines', []));

        return redirect()->route('statement-imports.show', $statementImport)
            ->with('success', __(':count movements imported.', ['count' => $created]));
    }

    public function updateReservedAmount(StatementImport $statementImport): RedirectResponse
    {
        $amount = $statementImport->suggestedReservedAmount();
        if ($amount === null || !$statementImport->isLatestForWallet()) {
            return redirect()->route('statement-imports.show', $statementImport)->with('error', __('Something went wrong'));
        }

        $statementImport->wallet->update(['reserved_amount' => $amount]);
        DashboardCacheService::clearAllByUser();

        return redirect()->route('statement-imports.show', $statementImport)
            ->with('success', __('Reserved amount of :wallet updated to :amount.', [
                'wallet' => $statementImport->wallet->name,
                'amount' => format_currency($amount),
            ]));
    }

    public function destroy(StatementImport $statementImport): RedirectResponse
    {
        $statementImport->delete();

        return redirect()->route('statement-imports.index')->with('success', __('Import discarded.'));
    }
}
