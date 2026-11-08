<?php

use App\Models\FinancialCategory;
use App\Models\User;
use App\Models\Wallet;
use Carbon\Carbon;

it('forecasts the balance at the end of the current month', function () {
    Carbon::setTestNow('2026-11-10 12:00');
    $this->actingAs(User::factory()->create());
    $bank = Wallet::create(['name' => 'Banco', 'type' => 'debit', 'active' => true]);
    $salary = FinancialCategory::create(['name' => 'Salário', 'type' => 'income', 'expected_total' => 2000]);
    $food = FinancialCategory::create(['name' => 'Supermercado', 'type' => 'expense', 'expected_total' => -300]);
    // above its forecast: nothing more to spend
    $fun = FinancialCategory::create(['name' => 'Lazer', 'type' => 'expense', 'expected_total' => -50]);

    foreach ([['Salário', 1000, 'income', $salary], ['Pingo Doce', 100, 'expense', $food], ['Cinema', 80, 'expense', $fun]] as [$description, $amount, $type, $category]) {
        $this->postJson(route('financial-movements.store'), [
            'description' => $description, 'date' => '2026-11-05', 'wallet_id' => $bank->id,
            'amount' => $amount, 'type' => $type, 'category_id' => $category->id,
        ])->assertCreated();
    }

    // the dashboard page itself runs MySQL-only queries: render its table partial
    $render = fn (string $month) => view('dashboard.category-table', [
        'categoryTable' => App\Services\CategoryMonthlyService::build($month, 6),
        'kpis' => App\Services\DashboardKpiService::for($month, []),
        'range' => 6,
    ])->render();

    // 820 in the bank + 1000 still to receive - 200 still to spend
    expect($render('2026-11'))
        ->toContain('Previsão no fim de novembro')
        ->toContain('falta receber +1.000,00')
        ->toContain('falta gastar −200,00')
        ->toContain('1.620,00');

    // a past month has no forecast to come
    expect($render('2026-10'))->not->toContain('Previsão no fim de');
    Carbon::setTestNow();
});

it('takes a negative forecast as the opposite of the category (a loss on an income)', function () {
    Carbon::setTestNow('2026-11-10 12:00');
    $this->actingAs(User::factory()->create());
    $room = FinancialCategory::create(['name' => 'Sala Dayana', 'type' => 'income']);
    $food = FinancialCategory::create(['name' => 'Supermercado', 'type' => 'expense']);

    $this->putJson(route('financial-categories.expected-values.bulk'), ['expected_values' => [$room->id => -130, $food->id => -300]])->assertOk();
    expect((float) $room->fresh()->expected_total)->toBe(-130.0)
        ->and($room->fresh()->forecast())->toBe(-130.0)
        ->and($food->fresh()->forecast())->toBe(300.0);

    // the category form keeps the sign too
    $this->patch(route('financial-categories.update', $room), ['name' => 'Sala Dayana', 'type' => 'income', 'expected_total' => '-130', 'active' => 1]);
    expect((float) $room->fresh()->expected_total)->toBe(-130.0);

    $table = App\Services\CategoryMonthlyService::build('2026-11', 6);
    $row = collect($table['groups']['income'])->firstWhere('name', 'Sala Dayana');
    expect($row->forecast)->toBe(-130.0);

    // the loss still to come lowers the month-end forecast: 0 in banks - 130 - 300
    $html = view('dashboard.category-table', ['categoryTable' => $table, 'kpis' => App\Services\DashboardKpiService::for('2026-11', []), 'range' => 6])->render();
    expect($html)->toContain('falta receber −130,00')->toContain('falta gastar −300,00')->toContain('-430,00');
    Carbon::setTestNow();
});
