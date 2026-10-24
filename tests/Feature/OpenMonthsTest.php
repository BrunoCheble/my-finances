<?php

use App\Models\FinancialBalance;
use App\Models\FinancialMovement;
use App\Models\User;
use App\Models\Wallet;
use App\Services\CalculateFinancialBalanceService;
use App\Services\OpenMonthsService;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-10-15');
    $this->actingAs(User::factory()->create());
});

afterEach(fn () => Carbon::setTestNow());

function walletWithMovement(string $name, string $date, float $amount, bool $active = true): Wallet
{
    $wallet = Wallet::create(['name' => $name, 'type' => 'debit', 'active' => $active]);
    $movement = FinancialMovement::create(['description' => 'x', 'date' => $date, 'wallet_id' => $wallet->id, 'amount' => $amount, 'type' => 'income']);
    app(CalculateFinancialBalanceService::class)->executeForMovements([$movement]);
    return $wallet;
}

function months(Wallet $wallet): array
{
    return FinancialBalance::where('wallet_id', $wallet->id)->orderBy('start_date')->get()
        ->mapWithKeys(fn ($b) => [substr($b->start_date, 0, 7) => (float) $b->calculated_balance])->all();
}

it('opens every month up to now for active wallets, carrying the balance', function () {
    $savings = walletWithMovement('Poupança', '2026-08-03', 500);
    $old = walletWithMovement('Antiga', '2026-08-03', 50, active: false);

    expect(app(OpenMonthsService::class)->execute())->toBe(2)
        ->and(months($savings))->toBe(['2026-08' => 500.0, '2026-09' => 500.0, '2026-10' => 500.0])
        ->and(months($old))->toBe(['2026-08' => 50.0])
        // nothing left to open
        ->and(app(OpenMonthsService::class)->execute())->toBe(0);
});

it('keeps the opened months in the cascade', function () {
    $savings = walletWithMovement('Poupança', '2026-08-03', 500);
    app(OpenMonthsService::class)->execute();

    $movement = FinancialMovement::create(['description' => 'y', 'date' => '2026-09-10', 'wallet_id' => $savings->id, 'amount' => 100, 'type' => 'income']);
    app(CalculateFinancialBalanceService::class)->executeForMovements([$movement]);

    expect(months($savings))->toBe(['2026-08' => 500.0, '2026-09' => 600.0, '2026-10' => 600.0]);
});

it('skips wallets with nothing to carry', function () {
    $empty = walletWithMovement('Zerada', '2026-08-03', 0);

    app(OpenMonthsService::class)->execute();

    expect(FinancialBalance::where('wallet_id', $empty->id)->count())->toBeLessThanOrEqual(1);
});

it('keeps inactive wallets out of the forms but in the movements history', function () {
    $active = walletWithMovement('Ativa', '2026-08-03', 10);
    $inactive = walletWithMovement('Encerrada', '2026-08-03', 20, active: false);

    $page = $this->get(route('financial-movements.index'))->assertOk();
    // the switcher still lists it (history)...
    $page->assertSee(route('financial-movements.index', ['wallet_id' => $inactive->id]), false);
    // ...and its movements are still there
    $this->get(route('financial-movements.index', ['wallet_id' => $inactive->id]))->assertOk()->assertSee('Encerrada');

    // but the statement upload refuses it
    $this->post(route('statement-imports.store'), [
        'file' => Illuminate\Http\UploadedFile::fake()->create('e.pdf', 10, 'application/pdf'),
        'wallet_id' => $inactive->id,
    ])->assertSessionHasErrors('wallet_id');
});
