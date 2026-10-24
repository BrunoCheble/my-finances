<?php

namespace App\Http\Controllers;

use App\Http\Requests\WalletRequest;
use App\Models\FinancialBalance;
use App\Models\FinancialMovement;
use App\Models\Wallet;
use App\Services\Cache\DashboardCacheService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function index(Request $request): View
    {
        $wallets = Wallet::orderBy('name')->get();
        $counts = FinancialMovement::selectRaw('wallet_id, count(*) as total')->groupBy('wallet_id')->pluck('total', 'wallet_id');
        $balances = FinancialBalance::orderBy('start_date')->get(['wallet_id', 'calculated_balance'])->groupBy('wallet_id')
            ->map(fn ($items) => (float) $items->last()->calculated_balance);

        foreach ($wallets as $wallet) {
            $wallet->movements_count = (int) ($counts[$wallet->id] ?? 0);
            $wallet->balance = (float) ($balances[$wallet->id] ?? 0);
        }

        return view('wallets.index', [
            'groups' => [
                'own' => $wallets->filter(fn ($w) => $w->active && !$w->is_third_party)->values(),
                'third_party' => $wallets->filter(fn ($w) => $w->active && $w->is_third_party)->values(),
                'inactive' => $wallets->reject(fn ($w) => $w->active)->values(),
            ],
            'openModal' => $request->input('modal'),
        ]);
    }

    // the forms live in modals on the index
    public function create(): RedirectResponse
    {
        return redirect()->route('wallets.index', ['modal' => 'wallet-new']);
    }

    public function edit($id): RedirectResponse
    {
        return redirect()->route('wallets.index', ['modal' => 'wallet-edit-' . (int) $id]);
    }

    public function store(WalletRequest $request): RedirectResponse
    {
        Wallet::create(self::normalized($request));
        DashboardCacheService::clearAllByUser();

        return redirect()->route('wallets.index')->with('success', __('Wallet created.'));
    }

    public function update(WalletRequest $request, Wallet $wallet): RedirectResponse
    {
        $wallet->update(self::normalized($request));
        DashboardCacheService::clearAllByUser();

        return redirect()->route('wallets.index')->with('success', __('Wallet updated.'));
    }

    public function destroy(Wallet $wallet): RedirectResponse
    {
        if ($wallet->financialMovements()->exists()) {
            return redirect()->route('wallets.index')->with('error', __('This wallet has movements: deactivate it instead of deleting.'));
        }

        $wallet->financialBalances()->delete();
        $wallet->delete();
        DashboardCacheService::clearAllByUser();

        return redirect()->route('wallets.index')->with('success', __('Wallet deleted.'));
    }

    /**
     * The held amount (valor cativo) is typed as a positive amount and stored negative,
     * because it is added to the displayed balance.
     */
    private static function normalized(WalletRequest $request): array
    {
        $data = $request->validated();
        $data['reserved_amount'] = -abs((float) ($data['reserved_amount'] ?? 0));
        $data['active'] = $request->boolean('active');
        return $data;
    }
}
