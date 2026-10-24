<?php

use App\Models\FinancialCategory;
use App\Models\FinancialMovement;
use App\Models\Loan;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Alerts\AlertService;
use App\Services\CalculateFinancialBalanceService;
use Illuminate\Support\Carbon;

function spend(Wallet $wallet, ?FinancialCategory $category, string $date, float $amount, string $description = 'Compra', string $type = 'expense', bool $alert = false): FinancialMovement
{
    return FinancialMovement::create([
        'description' => $description, 'date' => $date, 'wallet_id' => $wallet->id,
        'category_id' => $category?->id, 'amount' => $type === 'expense' ? -$amount : $amount, 'type' => $type, 'include_alert' => $alert,
    ]);
}

function keys(string $month): array
{
    return collect(AlertService::for($month))->pluck('level', 'key')->all();
}

beforeEach(function () {
    Carbon::setTestNow('2026-11-15');
    $this->actingAs(User::factory()->create());
    $this->bank = Wallet::create(['name' => 'Banco', 'type' => 'debit', 'active' => true]);
    $this->market = FinancialCategory::create(['name' => 'Mercado', 'type' => 'expense']);
});

afterEach(fn () => Carbon::setTestNow());

it('warns near the forecast and flags it when exceeded', function () {
    $this->market->update(['expected_total' => -400]);
    spend($this->bank, $this->market, '2026-10-10', 330);
    expect(keys('2026-10'))->toMatchArray(["budget-{$this->market->id}" => 'warning']);

    spend($this->bank, $this->market, '2026-10-20', 82);
    expect(keys('2026-10'))->toMatchArray(["budget-{$this->market->id}" => 'critical']);
});

it('only flags spending meaningfully above the usual', function () {
    foreach (['2026-04', '2026-05', '2026-06', '2026-07', '2026-08', '2026-09'] as $month) {
        spend($this->bank, $this->market, "$month-05", 100);
    }
    spend($this->bank, $this->market, '2026-10-05', 115);
    expect(keys('2026-10'))->not->toHaveKey("spending-{$this->market->id}");

    spend($this->bank, $this->market, '2026-10-06', 40);
    expect(keys('2026-10'))->toMatchArray(["spending-{$this->market->id}" => 'warning']);
});

it('uses the pace of the current month', function () {
    foreach (['2026-04', '2026-05', '2026-06', '2026-07', '2026-08', '2026-09'] as $month) {
        spend($this->bank, $this->market, "$month-05", 100);
    }
    spend($this->bank, $this->market, '2026-10-03', 60);

    Carbon::setTestNow('2026-10-05');
    expect(keys('2026-10'))->not->toHaveKey("spending-{$this->market->id}"); // too early

    Carbon::setTestNow('2026-10-10');
    $alert = collect(AlertService::for('2026-10'))->firstWhere('key', "spending-{$this->market->id}");
    expect($alert)->not->toBeNull()->and($alert['message'])->toContain('186');
});

it('finds unusual purchases and possible duplicates', function () {
    foreach (range(1, 6) as $i) {
        spend($this->bank, $this->market, "2026-0" . (3 + $i) . "-10", 20);
    }
    $big = spend($this->bank, $this->market, '2026-10-12', 100, 'Compra grande');
    $first = spend($this->bank, null, '2026-10-01', 13.99, 'NETFLIX.COM 1111');
    $second = spend($this->bank, null, '2026-10-02', 13.99, 'NETFLIX.COM 2222');

    $other = Wallet::create(['name' => 'Outra', 'type' => 'debit', 'active' => true]);
    spend($other, null, '2026-10-02', 13.99, 'NETFLIX.COM 3333');

    expect(keys('2026-10'))->toMatchArray([
        "outlier-{$big->id}" => 'warning',
        "duplicate-{$first->id}-{$second->id}" => 'warning',
    ]);
});

it('lists pending items: no category, negative wallet, old loans and reminders', function () {
    spend($this->bank, null, '2026-10-05', 50, 'Sem categoria');
    spend($this->bank, $this->market, '2026-10-06', 10, 'Pagar seguro', alert: true);
    $card = Wallet::create(['name' => 'Cartão', 'type' => 'credit', 'active' => true]);
    spend($card, $this->market, '2026-10-07', 500);
    app(CalculateFinancialBalanceService::class)->executeForMovements(FinancialMovement::all());

    $this->post(route('loans.store'), ['person' => 'João', 'amount' => 100, 'date' => '2026-07-01', 'wallet_id' => $this->bank->id]);
    $loan = Loan::sole();

    $alerts = keys('2026-10');
    expect($alerts)->toMatchArray([
        'uncategorized' => 'info',
        "overdrawn-{$this->bank->id}" => 'critical',
        "loan-{$loan->id}" => 'info',
    ])->and($alerts)->not->toHaveKey("overdrawn-{$card->id}")
        ->and(collect($alerts)->keys()->filter(fn ($k) => str_starts_with($k, 'reminder-')))->toHaveCount(1)
        // most important first
        ->and(array_values($alerts)[0])->toBe('critical');
});
