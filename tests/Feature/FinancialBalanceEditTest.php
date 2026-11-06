<?php

use App\Models\FinancialBalance;
use App\Models\FinancialMovement;
use App\Models\User;
use App\Models\Wallet;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->wallet = Wallet::create(['name' => 'Banco', 'type' => 'debit', 'active' => true]);
    $this->balance = FinancialBalance::create([
        'wallet_id' => $this->wallet->id, 'start_date' => '2026-09-01', 'end_date' => '2026-09-30',
        'initial_balance' => 0, 'total_expense' => 0, 'total_income' => 0, 'total_unidentified' => 0, 'calculated_balance' => 0,
    ]);
});

it('removes a balance without movements', function () {
    $this->delete(route('financial-balances.destroy', $this->balance))->assertSessionHas('success');

    expect(FinancialBalance::find($this->balance->id))->toBeNull();
});

it('refuses a balance with movements in its period', function () {
    FinancialMovement::create(['description' => 'x', 'date' => '2026-09-10', 'wallet_id' => $this->wallet->id, 'amount' => 10, 'type' => 'income']);

    $this->delete(route('financial-balances.destroy', $this->balance))->assertSessionHas('error');

    expect(FinancialBalance::find($this->balance->id))->not->toBeNull();
});

it('does not remove a balance of someone else', function () {
    $this->actingAs(User::factory()->create())
        ->delete(route('financial-balances.destroy', $this->balance))
        ->assertNotFound();

    expect(FinancialBalance::withoutGlobalScopes()->find($this->balance->id))->not->toBeNull();
});

it('edits the dates of a balance and recalculates it', function () {
    FinancialMovement::create(['description' => 'x', 'date' => '2026-10-03', 'wallet_id' => $this->wallet->id, 'amount' => 10, 'type' => 'income']);

    $this->patch(route('financial-balances.dates', $this->balance), ['start_date' => '2026-09-01', 'end_date' => '2026-10-05'])
        ->assertSessionHas('success');

    $balance = $this->balance->fresh();
    expect($balance->end_date)->toBe('2026-10-05')
        ->and((float) $balance->calculated_balance)->toBe(10.0);
});

it('refuses dates that overlap another balance of the wallet', function () {
    FinancialBalance::create([
        'wallet_id' => $this->wallet->id, 'start_date' => '2026-10-01', 'end_date' => '2026-10-31',
        'initial_balance' => 0, 'total_expense' => 0, 'total_income' => 0, 'total_unidentified' => 0, 'calculated_balance' => 5,
    ]);

    $this->patch(route('financial-balances.dates', $this->balance), ['start_date' => '2026-09-01', 'end_date' => '2026-10-10'])
        ->assertSessionHasErrors('start_date', null, 'dates' . $this->balance->id);

    expect($this->balance->fresh()->end_date)->toBe('2026-09-30');
});

it('refuses an end date before the start date', function () {
    $this->patch(route('financial-balances.dates', $this->balance), ['start_date' => '2026-09-10', 'end_date' => '2026-09-01'])
        ->assertSessionHasErrors('end_date', null, 'dates' . $this->balance->id);
});

it('adds a wallet with a carried balance to a month it is not in', function () {
    $savings = Wallet::create(['name' => 'Poupança', 'type' => 'debit', 'active' => false]);
    FinancialMovement::create(['description' => 'x', 'date' => '2026-07-10', 'wallet_id' => $savings->id, 'amount' => 500, 'type' => 'income']);
    app(\App\Services\CalculateFinancialBalanceService::class)->recalculateFrom($savings->id, '2026-07-10');

    $this->post(route('financial-balances.addWallet', '2026-09-01'), ['wallet_id' => $savings->id])->assertSessionHas('success');

    $added = FinancialBalance::where('wallet_id', $savings->id)->where('start_date', '2026-09-01')->sole();
    expect((float) $added->initial_balance)->toBe(500.0)
        ->and((float) $added->calculated_balance)->toBe(500.0);
});

it('does not add a wallet with nothing in the month', function () {
    $empty = Wallet::create(['name' => 'Vazia', 'type' => 'debit', 'active' => true]);

    $this->post(route('financial-balances.addWallet', '2026-09-01'), ['wallet_id' => $empty->id])->assertSessionHas('error');

    expect(FinancialBalance::where('wallet_id', $empty->id)->exists())->toBeFalse();
});

it('does not add a wallet that is already in the month', function () {
    $this->post(route('financial-balances.addWallet', '2026-09-01'), ['wallet_id' => $this->wallet->id])->assertSessionHas('error');

    expect(FinancialBalance::where('wallet_id', $this->wallet->id)->count())->toBe(1);
});

it('lists the wallets left out of a month with the balance they would bring, out of the totals', function () {
    $savings = Wallet::create(['name' => 'Poupança', 'type' => 'debit', 'active' => false]);
    FinancialMovement::create(['description' => 'x', 'date' => '2026-07-10', 'wallet_id' => $savings->id, 'amount' => 500, 'type' => 'income']);
    app(\App\Services\CalculateFinancialBalanceService::class)->recalculateFrom($savings->id, '2026-07-10');
    // a wallet with nothing to bring is not listed
    Wallet::create(['name' => 'Vazia', 'type' => 'debit', 'active' => true]);

    $september = \App\Services\FinancialBalanceAccordionService::execute()->firstWhere('startDate', '2026-09-01');

    expect($september->missing->map(fn ($row) => [$row->wallet->name, $row->carried])->all())->toBe([['Poupança', 500.0]])
        ->and($september->balances->pluck('wallet_id')->all())->not->toContain($savings->id);
});
