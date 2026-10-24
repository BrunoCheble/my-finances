<?php

use App\Enums\FinancialMovementType;
use App\Models\FinancialBalance;
use App\Models\FinancialMovement;
use App\Models\User;
use App\Models\Wallet;

function makeWallet(User $user, string $name): Wallet
{
    $wallet = new Wallet(['name' => $name, 'type' => 'debit', 'active' => true]);
    $wallet->user_id = $user->id;
    $wallet->save();
    return $wallet;
}

function balanceOf(Wallet $wallet, string $month): ?FinancialBalance
{
    return FinancialBalance::withoutGlobalScopes()
        ->where('wallet_id', $wallet->id)
        ->where('start_date', $month)
        ->first();
}

function storeMovement($test, array $data)
{
    return $test->postJson(route('financial-movements.store'), $data + [
        'description' => 'Teste',
        'type' => FinancialMovementType::INCOME,
    ])->assertCreated();
}

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->wallet = makeWallet($this->user, 'Banco');
    $this->other = makeWallet($this->user, 'Poupança');
});

it('propaga a alteração de um mês antigo para os meses seguintes', function () {
    storeMovement($this, ['date' => '2026-03-10', 'wallet_id' => $this->wallet->id, 'amount' => 50]);
    storeMovement($this, ['date' => '2026-01-10', 'wallet_id' => $this->wallet->id, 'amount' => 100]);

    expect((float) balanceOf($this->wallet, '2026-01-01')->calculated_balance)->toBe(100.0)
        ->and((float) balanceOf($this->wallet, '2026-03-01')->initial_balance)->toBe(100.0)
        ->and((float) balanceOf($this->wallet, '2026-03-01')->calculated_balance)->toBe(150.0);
});

it('devolve o saldo do mês de cada carteira ao criar uma transferência', function () {
    $response = storeMovement($this, [
        'date' => '2026-01-10',
        'wallet_id' => $this->wallet->id,
        'transfer_to_wallet_id' => $this->other->id,
        'type' => FinancialMovementType::TRANSFER,
        'amount' => 30,
    ]);

    $balances = collect($response->json('balances'))->pluck('calculated_balance', 'wallet_id');
    expect((float) $balances[$this->wallet->id])->toBe(-30.0)
        ->and((float) $balances[$this->other->id])->toBe(30.0);
});

it('recalcula a carteira e o mês antigos quando o movimento é editado', function () {
    storeMovement($this, ['date' => '2026-01-10', 'wallet_id' => $this->wallet->id, 'amount' => 100]);
    storeMovement($this, ['date' => '2026-02-10', 'wallet_id' => $this->wallet->id, 'amount' => 10]);
    $movement = FinancialMovement::where('amount', 100)->first();

    $this->patch(route('financial-movements.update', $movement), [
        'description' => 'Teste',
        'date' => '2026-01-10',
        'wallet_id' => $this->other->id,
        'type' => FinancialMovementType::INCOME,
        'amount' => 100,
    ])->assertRedirect();

    expect(balanceOf($this->wallet, '2026-01-01'))->toBeNull()
        ->and((float) balanceOf($this->wallet, '2026-02-01')->initial_balance)->toBe(0.0)
        ->and((float) balanceOf($this->wallet, '2026-02-01')->calculated_balance)->toBe(10.0)
        ->and((float) balanceOf($this->other, '2026-01-01')->calculated_balance)->toBe(100.0);
});

it('recalcula a carteira destino ao apagar uma transferência', function () {
    storeMovement($this, [
        'date' => '2026-01-10',
        'wallet_id' => $this->wallet->id,
        'transfer_to_wallet_id' => $this->other->id,
        'type' => FinancialMovementType::TRANSFER,
        'amount' => 30,
    ]);
    storeMovement($this, ['date' => '2026-02-10', 'wallet_id' => $this->other->id, 'amount' => 5]);
    $origin = FinancialMovement::whereNull('original_movement_id')->where('wallet_id', $this->wallet->id)->first();

    $this->deleteJson(route('financial-movements.delete', $origin->id))->assertOk();

    expect(balanceOf($this->other, '2026-01-01'))->toBeNull()
        ->and((float) balanceOf($this->other, '2026-02-01')->calculated_balance)->toBe(5.0);
});

it('corrige o saldo com um movimento de ajuste no 1º dia do mês', function () {
    storeMovement($this, ['date' => '2026-01-10', 'wallet_id' => $this->wallet->id, 'amount' => 100]);
    storeMovement($this, ['date' => '2026-02-10', 'wallet_id' => $this->wallet->id, 'amount' => 10]);
    $january = balanceOf($this->wallet, '2026-01-01');

    $this->patch(route('financial-balances.update', $january), ['real_balance' => 120])->assertRedirect();

    $adjustment = FinancialMovement::where('type', FinancialMovementType::ADJUSTMENT)->sole();
    expect($adjustment->date)->toBe('2026-01-01')
        ->and((float) $adjustment->amount)->toBe(20.0)
        ->and((float) balanceOf($this->wallet, '2026-01-01')->calculated_balance)->toBe(120.0)
        ->and((float) balanceOf($this->wallet, '2026-02-01')->calculated_balance)->toBe(130.0);
});

it('converte ajustes manuais antigos em movimentos preservando os saldos', function () {
    // January: 100 in movements; February: initial hand-edited to 150 (+50);
    // March: no movements, closing balance typed by hand (200)
    FinancialMovement::create(['description' => 'Salário', 'date' => '2026-01-10', 'wallet_id' => $this->wallet->id, 'amount' => 100, 'type' => 'income']);
    FinancialMovement::create(['description' => 'Bónus', 'date' => '2026-02-10', 'wallet_id' => $this->wallet->id, 'amount' => 10, 'type' => 'income']);
    $legacy = [
        ['2026-01-01', '2026-01-31', 0, 100, 100],
        ['2026-02-01', '2026-02-28', 150, 160, 10],
        ['2026-03-01', '2026-03-31', 160, 200, 0],
    ];
    foreach ($legacy as [$start, $end, $initial, $calculated, $income]) {
        FinancialBalance::create([
            'wallet_id' => $this->wallet->id, 'start_date' => $start, 'end_date' => $end,
            'initial_balance' => $initial, 'calculated_balance' => $calculated, 'total_income' => $income,
        ]);
    }

    $this->artisan('balances:rebuild')->assertSuccessful();

    $adjustments = FinancialMovement::where('type', FinancialMovementType::ADJUSTMENT)->orderBy('date')->get();
    expect($adjustments->pluck('date')->all())->toBe(['2026-02-01', '2026-03-01'])
        ->and($adjustments->pluck('amount')->map(fn ($a) => (float) $a)->all())->toBe([50.0, 40.0])
        ->and((float) balanceOf($this->wallet, '2026-02-01')->initial_balance)->toBe(100.0)
        ->and((float) balanceOf($this->wallet, '2026-02-01')->calculated_balance)->toBe(160.0)
        ->and((float) balanceOf($this->wallet, '2026-03-01')->calculated_balance)->toBe(200.0);

    // idempotent
    $this->artisan('balances:rebuild')->assertSuccessful();
    expect(FinancialMovement::where('type', FinancialMovementType::ADJUSTMENT)->count())->toBe(2);
});

it('não grava nada em dry-run', function () {
    FinancialBalance::create([
        'wallet_id' => $this->wallet->id, 'start_date' => '2026-01-01', 'end_date' => '2026-01-31',
        'initial_balance' => 70, 'calculated_balance' => 70,
    ]);

    $this->artisan('balances:rebuild', ['--dry-run' => true])->assertSuccessful();

    expect(FinancialMovement::count())->toBe(0)
        ->and((float) balanceOf($this->wallet, '2026-01-01')->initial_balance)->toBe(70.0);
});
