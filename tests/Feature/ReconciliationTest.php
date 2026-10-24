<?php

use App\Enums\FinancialMovementType;
use App\Models\FinancialBalance;
use App\Models\FinancialMovement;
use App\Models\MovementChange;
use App\Models\StatementImport;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Alerts\AlertService;
use App\Services\ReconciliationService;

function reconWallet(User $user, string $name): Wallet
{
    $wallet = new Wallet(['name' => $name, 'type' => 'debit', 'active' => true]);
    $wallet->user_id = $user->id;
    $wallet->save();
    return $wallet;
}

function reconBalance(Wallet $wallet, string $month): ?FinancialBalance
{
    return FinancialBalance::where('wallet_id', $wallet->id)->where('start_date', $month)->first();
}

function reconMovement($test, array $data)
{
    return $test->postJson(route('financial-movements.store'), $data + [
        'description' => 'Salário',
        'type' => FinancialMovementType::INCOME,
    ]);
}

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->wallet = reconWallet($this->user, 'Banco');
    $this->other = reconWallet($this->user, 'Poupança');

    reconMovement($this, ['date' => '2026-08-05', 'wallet_id' => $this->wallet->id, 'amount' => 1000])->assertCreated();
    reconMovement($this, ['date' => '2026-09-05', 'wallet_id' => $this->wallet->id, 'amount' => 200])->assertCreated();
});

it('stores the confirmed balance of a wallet month', function () {
    $august = reconBalance($this->wallet, '2026-08-01');

    $this->post(route('financial-balances.reconcile', $august))->assertRedirect();

    $august->refresh();
    expect((float) $august->reconciled_balance)->toBe(1000.0)
        ->and($august->reconciled_source)->toBe('manual')
        ->and($august->is_diverged)->toBeFalse();
});

it('confirms every wallet of a month at once', function () {
    reconMovement($this, ['date' => '2026-08-10', 'wallet_id' => $this->other->id, 'amount' => 50])->assertCreated();

    $this->post(route('financial-balances.reconcileMonth', '2026-08-01'))->assertRedirect();

    expect(reconBalance($this->wallet, '2026-08-01')->is_reconciled)->toBeTrue()
        ->and(reconBalance($this->other, '2026-08-01')->is_reconciled)->toBeTrue()
        ->and(reconBalance($this->wallet, '2026-09-01')->is_reconciled)->toBeFalse();
});

it('asks for confirmation before saving into a confirmed month', function () {
    app(ReconciliationService::class)->reconcile(reconBalance($this->wallet, '2026-08-01'));

    reconMovement($this, ['date' => '2026-08-20', 'wallet_id' => $this->wallet->id, 'amount' => 30])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('confirm_reconciled');

    // a month after the confirmed one is free
    reconMovement($this, ['date' => '2026-09-20', 'wallet_id' => $this->wallet->id, 'amount' => 30])->assertCreated();

    reconMovement($this, ['date' => '2026-08-20', 'wallet_id' => $this->wallet->id, 'amount' => 30, 'confirm_reconciled' => 1])->assertCreated();
});

it('warns about an earlier month too, since it cascades into the confirmed one', function () {
    app(ReconciliationService::class)->reconcile(reconBalance($this->wallet, '2026-09-01'));

    reconMovement($this, ['date' => '2026-07-15', 'wallet_id' => $this->wallet->id, 'amount' => 30])
        ->assertJsonValidationErrors('confirm_reconciled');
});

it('detects the divergence and lists what changed after the confirmation', function () {
    app(ReconciliationService::class)->reconcile(reconBalance($this->wallet, '2026-09-01'));

    reconMovement($this, ['date' => '2026-08-20', 'wallet_id' => $this->wallet->id, 'amount' => 45, 'description' => 'Engano', 'confirm_reconciled' => 1])->assertCreated();

    $september = reconBalance($this->wallet, '2026-09-01');
    expect((float) $september->calculated_balance)->toBe(1245.0)
        ->and($september->is_diverged)->toBeTrue()
        ->and($september->reconciliation_difference)->toBe(45.0);

    $changes = ReconciliationService::changesSince($september);
    expect($changes)->toHaveCount(1)
        ->and($changes->first()->action)->toBe('created')
        ->and($changes->first()->description)->toBe('Engano');

    $alert = collect(AlertService::for('2026-10'))->firstWhere('key', "reconciliation-{$september->id}");
    expect($alert['level'])->toBe('critical');
});

it('logs edits and deletions of confirmed months', function () {
    $movement = FinancialMovement::where('date', '2026-08-05')->first();
    app(ReconciliationService::class)->reconcile(reconBalance($this->wallet, '2026-08-01'));

    $this->patch(route('financial-movements.update', $movement), [
        'description' => 'Salário', 'type' => FinancialMovementType::INCOME, 'date' => '2026-08-05',
        'wallet_id' => $this->wallet->id, 'amount' => 900, 'confirm_reconciled' => 1,
    ])->assertRedirect();

    // without the confirmation the delete is refused
    $this->delete(route('financial-movements.destroy', $movement))->assertSessionHasErrors('confirm_reconciled');
    expect(FinancialMovement::find($movement->id))->not->toBeNull();

    $this->delete(route('financial-movements.destroy', $movement), ['confirm_reconciled' => 1])->assertRedirect();

    $log = MovementChange::orderBy('id')->get();
    expect($log->pluck('action')->all())->toBe(['updated', 'deleted'])
        ->and((float) $log[0]->old_amount)->toBe(1000.0)
        ->and((float) $log[0]->new_amount)->toBe(900.0)
        ->and((float) $log[1]->old_amount)->toBe(900.0);
});

it('does not log changes of months that were never confirmed', function () {
    reconMovement($this, ['date' => '2026-08-20', 'wallet_id' => $this->wallet->id, 'amount' => 30])->assertCreated();

    expect(MovementChange::count())->toBe(0);
});

it('brings a diverged month back with an adjustment, or accepts the new balance', function () {
    $september = reconBalance($this->wallet, '2026-09-01');
    app(ReconciliationService::class)->reconcile($september);
    reconMovement($this, ['date' => '2026-09-20', 'wallet_id' => $this->wallet->id, 'amount' => 80, 'type' => FinancialMovementType::EXPENSE, 'confirm_reconciled' => 1]);

    $september->refresh();
    expect($september->reconciliation_difference)->toBe(-80.0);

    $this->post(route('financial-balances.restore', $september))->assertRedirect();
    $september->refresh();
    expect($september->is_diverged)->toBeFalse()
        ->and(FinancialMovement::where('type', FinancialMovementType::ADJUSTMENT)->value('amount'))->toEqual(80);

    // accepting: the confirmation moves to the current balance
    reconMovement($this, ['date' => '2026-09-21', 'wallet_id' => $this->wallet->id, 'amount' => 10, 'confirm_reconciled' => 1]);
    $this->post(route('financial-balances.reconcile', $september))->assertRedirect();
    expect((float) $september->refresh()->reconciled_balance)->toBe(1210.0)
        ->and($september->is_diverged)->toBeFalse();

    $this->delete(route('financial-balances.unreconcile', $september))->assertRedirect();
    expect($september->refresh()->is_reconciled)->toBeFalse();
});

it('keeps the mirror of an edited transfer instead of recreating it', function () {
    reconMovement($this, [
        'date' => '2026-09-10', 'wallet_id' => $this->wallet->id, 'transfer_to_wallet_id' => $this->other->id,
        'type' => FinancialMovementType::TRANSFER, 'amount' => 100,
    ])->assertCreated();
    $original = FinancialMovement::whereNotNull('original_movement_id')->first()->originalMovement;
    $mirrorId = $original->destinationMovement->id;

    $this->patch(route('financial-movements.update', $original), [
        'description' => 'Poupar', 'type' => FinancialMovementType::TRANSFER, 'date' => '2026-09-10',
        'wallet_id' => $this->wallet->id, 'transfer_to_wallet_id' => $this->other->id, 'amount' => 150,
    ])->assertRedirect();

    $mirror = FinancialMovement::find($mirrorId);
    expect($mirror)->not->toBeNull()
        ->and((float) $mirror->amount)->toBe(150.0)
        ->and((float) reconBalance($this->other, '2026-09-01')->calculated_balance)->toBe(150.0);
});

it('confirms the month automatically when the statement matches the bank balance', function () {
    $service = app(ReconciliationService::class);
    $import = new StatementImport(['wallet_id' => $this->wallet->id, 'period_end' => '2026-09-30', 'closing_balance' => 1200]);
    expect($service->reconcileFromStatement($import)?->reconciled_source)->toBe('statement');

    $other = new StatementImport(['wallet_id' => $this->other->id, 'period_end' => '2026-09-30', 'closing_balance' => 999]);
    reconMovement($this, ['date' => '2026-09-01', 'wallet_id' => $this->other->id, 'amount' => 10])->assertCreated();
    expect($service->reconcileFromStatement($other))->toBeNull();

    // statement ending mid-month, with movements registered after it: not comparable
    $mid = new StatementImport(['wallet_id' => $this->wallet->id, 'period_end' => '2026-09-04', 'closing_balance' => 1000]);
    reconMovement($this, ['date' => '2026-09-03', 'wallet_id' => $this->other->id, 'amount' => 10]);
    expect($service->reconcileFromStatement($mid))->toBeNull();
});
