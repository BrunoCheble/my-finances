<?php

namespace App\Http\Controllers;

use App\Enums\FinancialMovementType;
use App\Models\Loan;
use App\Models\Wallet;
use App\Services\Cache\DashboardCacheService;
use App\Services\CalculateFinancialBalanceService;
use App\Services\LoanService;
use App\Services\SaveFinancialMovementService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class LoanController extends Controller
{
    public function index(): View
    {
        $loans = Loan::with(['movements.wallet'])->orderByRaw("status = 'open' desc")->latest('updated_at')->get();
        $open = $loans->where('status', Loan::OPEN)->values();
        $closed = $loans->where('status', '!=', Loan::OPEN)->values();
        $wallets = Wallet::own()->where('active', true)->orderBy('name')->get();

        return view('loans.index', compact('open', 'closed', 'wallets'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $this->record($data, LoanService::LEND, __('Loan to :person', ['person' => trim($data['person'])]));

        return redirect()->route('loans.index')->with('success', __('Loan registered.'));
    }

    public function receive(Request $request, Loan $loan): RedirectResponse
    {
        $data = $this->validated($request, withPerson: false);
        $data['person'] = $loan->person;
        $this->record($data, LoanService::RECEIVE, __('Payment from :person', ['person' => $loan->person]));

        return redirect()->route('loans.index')->with('success', __('Payment registered.'));
    }

    public function writeOff(Loan $loan, LoanService $loanService): RedirectResponse
    {
        $loss = $loanService->writeOff($loan, now()->toDateString());
        DashboardCacheService::clearAllByUser();

        return redirect()->route('loans.index')->with('success', __(':amount written off for :person.', [
            'amount' => format_currency($loss),
            'person' => $loan->person,
        ]));
    }

    private function validated(Request $request, bool $withPerson = true): array
    {
        return $request->validate(array_filter([
            'person' => $withPerson ? ['required', 'string', 'max:255'] : null,
            'amount' => ['required', 'numeric', 'gt:0'],
            'date' => ['required', 'date'],
            'wallet_id' => ['required', Rule::exists('wallets', 'id')->where('user_id', auth()->id())],
        ]));
    }

    private function record(array $data, string $direction, string $description): void
    {
        DB::transaction(function () use ($data, $direction, $description) {
            $movements = app(SaveFinancialMovementService::class)->execute([
                'description' => $description,
                'date' => $data['date'],
                'wallet_id' => $data['wallet_id'],
                'amount' => $data['amount'],
                'type' => FinancialMovementType::LOAN,
                'loan_person' => $data['person'],
                'loan_direction' => $direction,
            ]);
            app(CalculateFinancialBalanceService::class)->executeForMovements($movements);
            DashboardCacheService::clearAllByUser();
        });
    }
}
