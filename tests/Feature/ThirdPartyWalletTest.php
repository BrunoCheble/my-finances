<?php

use App\Enums\FinancialMovementType;
use App\Models\User;
use App\Models\Wallet;
use App\Services\CalculateFinancialBalanceService;
use App\Services\FinancialBalanceAccordionService;
use App\Services\SaveFinancialMovementService;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->bank = Wallet::create(['name' => 'Banco', 'type' => Wallet::TYPE_DEBIT, 'active' => true]);
    $this->savings = Wallet::create(['name' => 'Poupança', 'type' => Wallet::TYPE_DEBIT, 'active' => true]);
    $this->daughter = Wallet::create(['name' => 'Filha', 'type' => Wallet::TYPE_THIRD_PARTY, 'active' => true]);

    $save = app(SaveFinancialMovementService::class);
    $calculate = app(CalculateFinancialBalanceService::class);
    $movements = collect([
        // 1000 in the bank, 100 of it belongs to the daughter
        ['description' => 'Salário', 'date' => '2026-08-05', 'wallet_id' => $this->bank->id, 'amount' => 900, 'type' => FinancialMovementType::INCOME],
        ['description' => 'Dinheiro da filha', 'date' => '2026-08-06', 'wallet_id' => $this->daughter->id,
            'transfer_to_wallet_id' => $this->bank->id, 'amount' => 100, 'type' => FinancialMovementType::TRANSFER],
        // the savings account only moved in July: not in the newest month
        ['description' => 'Depósito', 'date' => '2026-07-01', 'wallet_id' => $this->savings->id, 'amount' => 500, 'type' => FinancialMovementType::INCOME],
    ])->flatMap(fn ($data) => $save->execute($data));
    $calculate->executeForMovements($movements);
});

it('accepts the third-party money type', function () {
    $this->post(route('wallets.store'), ['name' => 'Sobrinho', 'type' => 'third_party', 'active' => 1])->assertRedirect();

    expect(Wallet::where('name', 'Sobrinho')->sole()->is_third_party)->toBeTrue();
});

it('splits net worth and money in banks using the latest balance of every wallet', function () {
    $summary = FinancialBalanceAccordionService::getSummary(FinancialBalanceAccordionService::execute());

    expect((float) $summary['net_worth'])->toBe(1400.0)   // 1000 + 500 - 100
        ->and((float) $summary['in_banks'])->toBe(1500.0)   // 1000 + 500
        ->and((float) $summary['third_party'])->toBe(100.0);
});

it('keeps third-party wallets out of the statement upload', function () {
    $this->get(route('statement-imports.index'))->assertOk()->assertSee('Poupança')->assertDontSee('Filha');

    $this->post(route('statement-imports.store'), [
        'file' => UploadedFile::fake()->create('extrato.pdf', 10, 'application/pdf'),
        'wallet_id' => $this->daughter->id,
    ])->assertSessionHasErrors('wallet_id');
});
