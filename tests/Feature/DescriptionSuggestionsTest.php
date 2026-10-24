<?php

use App\Enums\FinancialMovementType;
use App\Models\FinancialCategory;
use App\Models\FinancialMovement;
use App\Models\User;
use App\Models\Wallet;

function suggestionMovement(User $user, Wallet $wallet, string $description, string $date, float $amount, ?FinancialCategory $category = null): void
{
    $movement = new FinancialMovement([
        'description' => $description, 'date' => $date, 'wallet_id' => $wallet->id, 'amount' => $amount,
        'type' => $amount < 0 ? FinancialMovementType::EXPENSE : FinancialMovementType::INCOME, 'category_id' => $category?->id,
    ]);
    $movement->user_id = $user->id;
    $movement->save();
}

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->wallet = new Wallet(['name' => 'Banco', 'type' => 'debit', 'active' => true]);
    $this->wallet->user_id = $this->user->id;
    $this->wallet->save();
    $this->streaming = new FinancialCategory(['name' => 'Streaming', 'type' => 'expense', 'active' => true]);
    $this->streaming->user_id = $this->user->id;
    $this->streaming->save();
});

it('suggests used descriptions, grouping purchase codes and keeping the latest', function () {
    suggestionMovement($this->user, $this->wallet, 'NETFLIX.COM 55551234', '2026-08-25', -13.99, $this->streaming);
    suggestionMovement($this->user, $this->wallet, 'NETFLIX.COM 55559999', '2026-09-25', -14.99, $this->streaming);
    suggestionMovement($this->user, $this->wallet, 'Supermercado', '2026-09-10', -50);

    $items = $this->getJson(route('financial-movements.suggestions', ['q' => 'netf']))->assertOk()->json();

    expect($items)->toHaveCount(1)
        ->and($items[0]['description'])->toBe('NETFLIX.COM 55559999')
        ->and($items[0]['category'])->toBe('Streaming')
        ->and($items[0]['amount'])->toEqual(-14.99)
        ->and($items[0]['uses'])->toBe(2);
});

it('puts descriptions starting with the term first', function () {
    suggestionMovement($this->user, $this->wallet, 'Pingo Doce', '2026-09-01', -20);
    suggestionMovement($this->user, $this->wallet, 'Café do Pingo', '2026-09-02', -2);
    suggestionMovement($this->user, $this->wallet, 'Café do Pingo', '2026-09-03', -2);

    $items = $this->getJson(route('financial-movements.suggestions', ['q' => 'pingo']))->json();

    expect(array_column($items, 'description'))->toBe(['Pingo Doce', 'Café do Pingo']);
});

it('only searches the user own movements and needs two letters', function () {
    $other = User::factory()->create();
    $wallet = new Wallet(['name' => 'Outro', 'type' => 'debit', 'active' => true]);
    $wallet->user_id = $other->id;
    $wallet->save();
    suggestionMovement($other, $wallet, 'Segredo alheio', '2026-09-01', -5);

    expect($this->getJson(route('financial-movements.suggestions', ['q' => 'segredo']))->json())->toBe([])
        ->and($this->getJson(route('financial-movements.suggestions', ['q' => 's']))->json())->toBe([]);
});

it('treats % and _ as plain text', function () {
    suggestionMovement($this->user, $this->wallet, 'Desconto 10%', '2026-09-01', 5);
    suggestionMovement($this->user, $this->wallet, 'Desconto 100', '2026-09-02', 5);

    expect(array_column($this->getJson(route('financial-movements.suggestions', ['q' => '10%']))->json(), 'description'))->toBe(['Desconto 10%']);
});
