<?php

namespace App\Services;

use App\Models\FinancialCategory;
use App\Models\FinancialMovement;
use Carbon\Carbon;

class FinancialCategorySummaryService
{

    private static function sumByMonth($groupedMovements, $allMonths, callable $filter)
    {
        return collect($allMonths)->mapWithKeys(function ($month) use ($groupedMovements, $filter) {
            $total = $groupedMovements->reduce(function ($carry, $category) use ($month, $filter) {
                $amount = $category[$month] ?? 0;
                return $filter($carry, $amount);
            }, 0);

            return [$month => $total];
        });
    }

    public static function execute()
    {
        $movements = FinancialMovement::where('category_id', '!=', null)->get();

        $categories = FinancialCategory::orderBy('type')->orderBy('name')->get();

        // Obter todos os meses únicos presentes nos movimentos
        $allMonths = $movements->pluck('date')
            ->map(fn($date) => Carbon::parse($date)->format('m/Y'))
            ->unique()
            ->sort()
            ->values()
            ->toArray();

        $lastMonth = $allMonths[count($allMonths) - 1];
        $allMonths[] = 'Previsão';

        // Agrupar movimentos por categoria e mês, garantindo que todos os meses existam
        $groupedMovements = $movements->groupBy('category_id')->map(function ($categoryMovements) use ($allMonths) {
            // Agrupar cada categoria pelos meses
            $monthlyData = $categoryMovements->groupBy(fn($movement) => Carbon::parse($movement->date)->format('m/Y'))
                ->map(fn($monthlyMovements) =>
                    $monthlyMovements->reduce(fn($carry, $movement) =>
                        $carry + ($movement->isDebit ? -$movement->amount : $movement->amount), 0
                    )
                );

            // Garantir que todos os meses estejam presentes (preenchendo com 0 se necessário)
            return collect($allMonths)->mapWithKeys(fn($month) => [$month => $monthlyData[$month] ?? 0]);
        });

        // Adicionar a previsão para cada categoria
        $expectedTotal = $categories->pluck('expected_total', 'id');
        $groupedMovements = $groupedMovements->map(
            function ($months, $category) use ($lastMonth, $expectedTotal) {
                $expected = $expectedTotal[$category];
                $months['Previsão'] = $expected != 0 ? $expected : $months[$lastMonth];
                return $months;
            }
        );

        // Garantir que TODAS as categorias tenham todos os meses
        $summary = $categories->mapWithKeys(function ($category) use ($groupedMovements, $allMonths) {
            return [$category->id => collect($allMonths)->mapWithKeys(fn($month) => [
                $month => $groupedMovements[$category->id][$month] ?? 0
            ])];
        });

        $categoriesList = $categories->pluck('name', 'id');
        $summary = $summary->map(function($category, $key) use ($categoriesList) {
            return (object) [
                'category' => $categoriesList[$key],
                'months' => $category
            ];
        });

        $percentage = self::getPercentagesAllMonths($groupedMovements, $allMonths, $summary);
        $total = self::sumByMonth($groupedMovements, $allMonths, fn($carry, $amount) => $carry + $amount);

        return (object) [
            'summary' => $summary,
            'percentages' => $percentage,
            'allMonths' => $allMonths,
            'total' => $total
        ];
    }

    private static function getPercentagesAllMonths($groupedMovements, $allMonths, $summary)
    {
        $totalIncome = self::sumByMonth($groupedMovements, $allMonths, fn($carry, $amount) => $amount > 0 ? $carry + $amount : $carry);
        $totalExpense = self::sumByMonth($groupedMovements, $allMonths, fn($carry, $amount) => $amount < 0 ? $carry - $amount : $carry);

        return $summary->map(
            function ($category, $key) use ($totalIncome, $totalExpense) {
                return (object) [
                    'category' => $category->category,
                    'months' => $category->months->mapWithKeys(
                        function ($amount, $month) use ($totalIncome, $totalExpense) {
                            $total = $amount > 0 ? $totalIncome[$month] : $totalExpense[$month];
                            $percentage = $total != 0 ? round(($amount / $total) * 100, 2) : 0;
                            return [$month => $percentage];
                        }
                    )->toArray()
                ];
            }
        );
    }
}
