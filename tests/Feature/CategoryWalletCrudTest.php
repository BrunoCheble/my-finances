<?php

use App\Models\FinancialCategory;
use App\Models\FinancialMovement;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->wallet = Wallet::create(['name' => 'Banco', 'type' => 'debit', 'active' => true]);
});

it('stores a positive expense forecast as negative and keeps income positive', function () {
    $this->post(route('financial-categories.store'), ['name' => 'Mercado', 'type' => 'expense', 'expected_total' => 300, 'active' => 1])->assertRedirect();
    $this->post(route('financial-categories.store'), ['name' => 'Salário', 'type' => 'income', 'expected_total' => 2000, 'active' => 1]);

    expect((float) FinancialCategory::where('name', 'Mercado')->value('expected_total'))->toBe(-300.0)
        ->and((float) FinancialCategory::where('name', 'Salário')->value('expected_total'))->toBe(2000.0);
});

it('moves the movements to another category before deleting', function () {
    $old = FinancialCategory::create(['name' => 'Mercadinho', 'type' => 'expense']);
    $target = FinancialCategory::create(['name' => 'Mercado', 'type' => 'expense']);
    FinancialMovement::create(['description' => 'x', 'date' => '2026-10-01', 'wallet_id' => $this->wallet->id, 'category_id' => $old->id, 'amount' => -10, 'type' => 'expense']);

    $this->delete(route('financial-categories.destroy', $old))->assertSessionHas('error');
    expect(FinancialCategory::find($old->id))->not->toBeNull();

    $this->delete(route('financial-categories.destroy', $old), ['move_to' => $target->id])->assertSessionHas('success');
    expect(FinancialCategory::find($old->id))->toBeNull()
        ->and(FinancialMovement::sole()->category_id)->toBe($target->id);
});

it('imports categories from CSV skipping existing names and invalid lines', function () {
    FinancialCategory::create(['name' => 'Mercado', 'type' => 'expense']);
    $csv = UploadedFile::fake()->createWithContent('categorias.csv', "expense,Mercado\nexpense,Transporte\nincome,Salário\nnope,Errada\n");

    $this->post(route('financial-categories.import'), ['file' => $csv])->assertSessionHas('success');

    expect(FinancialCategory::pluck('name')->sort()->values()->all())->toBe(['Mercado', 'Salário', 'Transporte']);
});

it('lets two users have categories and wallets with the same name', function () {
    FinancialCategory::create(['name' => 'Mercado', 'type' => 'expense']);

    $this->actingAs(User::factory()->create());
    $this->post(route('financial-categories.store'), ['name' => 'Mercado', 'type' => 'expense', 'active' => 1])->assertSessionHas('success');
    $this->post(route('wallets.store'), ['name' => 'Banco', 'type' => 'debit', 'active' => 1])->assertSessionHas('success');

    expect(FinancialCategory::withoutGlobalScopes()->where('name', 'Mercado')->count())->toBe(2)
        ->and(Wallet::withoutGlobalScopes()->where('name', 'Banco')->count())->toBe(2);
});

it('stores the held amount as negative and refuses deleting a wallet with movements', function () {
    $this->patch(route('wallets.update', $this->wallet), ['name' => 'Banco', 'type' => 'debit', 'active' => 1, 'reserved_amount' => 45.5])->assertSessionHas('success');
    expect((float) $this->wallet->fresh()->reserved_amount)->toBe(-45.5);

    FinancialMovement::create(['description' => 'x', 'date' => '2026-10-01', 'wallet_id' => $this->wallet->id, 'amount' => 10, 'type' => 'income']);
    $this->delete(route('wallets.destroy', $this->wallet))->assertSessionHas('error');
    expect(Wallet::find($this->wallet->id))->not->toBeNull();
});

it('renders both screens', function () {
    FinancialCategory::create(['name' => 'Mercado', 'type' => 'expense', 'expected_total' => -100]);
    $this->get(route('financial-categories.index'))->assertOk()->assertSee('Mercado');
    $this->get(route('wallets.index'))->assertOk()->assertSee('Banco');
    $this->get(route('wallets.create'))->assertRedirect(route('wallets.index', ['modal' => 'wallet-new']));
});
