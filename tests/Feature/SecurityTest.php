<?php

use App\Enums\FinancialMovementType;
use App\Models\FinancialMovement;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Alerts\CustomMovementAlert;
use App\Services\GetFinancialMovementService;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->actingAs($this->owner);
    $this->wallet = Wallet::create(['name' => 'Banco', 'type' => 'debit', 'active' => true]);
});

it('escapes movement descriptions inside dashboard alerts', function () {
    FinancialMovement::create([
        'description' => '<script>alert(1)</script>', 'date' => '2026-10-05', 'wallet_id' => $this->wallet->id,
        'amount' => -10, 'type' => 'expense', 'include_alert' => true,
    ]);

    $message = CustomMovementAlert::check('2026-10')[0]['message'];

    expect($message)->not->toContain('<script>')->and($message)->toContain('&lt;script&gt;');
});

it('does not accept a wallet of another user', function () {
    $other = User::factory()->create();
    $foreign = new Wallet(['name' => 'Alheia', 'type' => 'debit', 'active' => true]);
    $foreign->user_id = $other->id;
    $foreign->save();

    $this->postJson(route('financial-movements.store'), [
        'description' => 'Invasão', 'date' => '2026-10-05', 'wallet_id' => $foreign->id,
        'amount' => 10, 'type' => FinancialMovementType::INCOME,
    ])->assertStatus(422)->assertJsonValidationErrors('wallet_id');

    $this->post(route('financial-balances.store'), [
        'wallets' => [$foreign->id], 'initial_balance' => 100, 'start_date' => '2026-10-01', 'end_date' => '2026-10-31',
    ])->assertSessionHasErrors('wallets.0');

    expect(FinancialMovement::withoutGlobalScopes()->count())->toBe(0);
});

it('ignores unknown columns for sorting and filtering', function () {
    FinancialMovement::create(['description' => 'A', 'date' => '2026-10-05', 'wallet_id' => $this->wallet->id, 'amount' => -10, 'type' => 'expense']);

    $result = GetFinancialMovementService::execute('user_id', '%', 'user_id; drop table users', 'sideways', null, null, null, false);

    expect($result)->toHaveCount(1);
});
