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
