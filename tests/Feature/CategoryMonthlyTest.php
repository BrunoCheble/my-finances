<?php

use App\Models\FinancialCategory;
use App\Models\FinancialMovement;
use App\Models\User;
use App\Models\Wallet;
use App\Services\CategoryMonthlyService;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-10-20');
    $this->actingAs(User::factory()->create());
    $this->wallet = Wallet::create(['name' => 'Banco', 'type' => 'debit', 'active' => true]);
    $this->market = FinancialCategory::create(['name' => 'Mercado', 'type' => 'expense', 'expected_total' => -150]);
    $this->salary = FinancialCategory::create(['name' => 'Salário', 'type' => 'income']);
    $this->unused = FinancialCategory::create(['name' => 'Nada', 'type' => 'expense']);

    $add = fn ($category, $date, $amount) => FinancialMovement::create([
        'description' => 'x', 'date' => $date, 'wallet_id' => $this->wallet->id, 'category_id' => $category->id,
        'amount' => $amount, 'type' => $amount < 0 ? 'expense' : 'income',
    ]);
    $add($this->market, '2026-08-10', -100);
    $add($this->market, '2026-09-10', -200);
    $add($this->market, '2026-10-10', -50); // running month
    $add($this->salary, '2026-09-01', 2000);
});

afterEach(fn () => Carbon::setTestNow());

it('averages only complete months since the first movement', function () {
    $market = collect(CategoryMonthlyService::build('2026-10', 6)['groups']['expense'])->firstWhere('name', 'Mercado');

    // May-July have no data at all, October is still running
    expect($market->average)->toBe(150.0);
});

it('groups expenses and income with positive amounts and totals', function () {
    $table = CategoryMonthlyService::build('2026-10', 3);
    $market = collect($table['groups']['expense'])->firstWhere('name', 'Mercado');

    expect(collect($table['months'])->pluck('key')->all())->toBe(['2026-08', '2026-09', '2026-10'])
        ->and($market->values)->toBe(['2026-08' => 100.0, '2026-09' => 200.0, '2026-10' => 50.0])
        // the running month stays out of the average
        ->and($market->average)->toBe(150.0)
        ->and($market->forecast)->toBe(150.0)
        ->and($table['totals']['income']['2026-09'])->toBe(2000.0)
        ->and($table['result']['2026-09'])->toBe(1800.0)
        ->and(collect($table['groups']['expense'])->firstWhere('name', 'Nada')->empty)->toBeTrue();
});

it('stores expense forecasts as negative values', function () {
    $this->putJson(route('financial-categories.expected-values.bulk'), [
        'expected_values' => [$this->market->id => -180, $this->salary->id => 2100],
    ])->assertOk();

    expect((float) $this->market->fresh()->expected_total)->toBe(-180.0)
        ->and((float) $this->salary->fresh()->expected_total)->toBe(2100.0);
});
