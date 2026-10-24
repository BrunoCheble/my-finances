<?php

use App\Enums\FinancialMovementType;
use App\Models\FinancialCategory;
use App\Models\User;
use App\Models\Wallet;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->wallet = new Wallet(['name' => 'Banco', 'type' => 'debit', 'active' => true]);
    $this->wallet->user_id = $this->user->id;
    $this->wallet->save();
    $this->market = new FinancialCategory(['name' => 'Mercado', 'type' => 'expense', 'active' => true, 'expected_total' => -200]);
    $this->market->user_id = $this->user->id;
    $this->market->save();
});

function saveExpense($test, float $amount, string $date = '2026-10-10', string $type = FinancialMovementType::EXPENSE)
{
    return $test->postJson(route('financial-movements.store'), [
        'description' => 'Compra', 'type' => $type, 'date' => $date, 'amount' => $amount,
        'wallet_id' => $test->wallet->id, 'category_id' => $test->market->id,
    ])->assertCreated();
}

it('answers how the category stands in the month after saving', function () {
    saveExpense($this, 120);
    saveExpense($this, 50, '2026-09-10'); // other month: not counted
    $category = saveExpense($this, 50)->json('category');

    expect($category['name'])->toBe('Mercado')
        ->and($category['total'])->toEqual(170)
        ->and($category['forecast'])->toEqual(200)
        ->and($category['percent'])->toBe(85)
        ->and($category['left'])->toEqual(30)
        ->and($category['level'])->toBe('close')
        ->and($category['text'])->toContain('Mercado');
});

it('counts a refund down and flags going over the forecast', function () {
    saveExpense($this, 230);
    $category = saveExpense($this, 10, '2026-10-12', FinancialMovementType::REFUND)->json('category');

    expect($category['total'])->toEqual(220)
        ->and($category['left'])->toEqual(-20)
        ->and($category['level'])->toBe('over');
});

it('has no category answer for a transfer', function () {
    $other = new Wallet(['name' => 'Poupança', 'type' => 'debit', 'active' => true]);
    $other->user_id = $this->user->id;
    $other->save();

    $response = $this->postJson(route('financial-movements.store'), [
        'description' => 'Poupar', 'type' => FinancialMovementType::TRANSFER, 'date' => '2026-10-10', 'amount' => 10,
        'wallet_id' => $this->wallet->id, 'transfer_to_wallet_id' => $other->id,
    ])->assertCreated();

    expect($response->json('category'))->toBeNull();
});
