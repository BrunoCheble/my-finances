<?php

namespace App\Services\Alerts;

use App\Models\FinancialCategory;
use App\Models\FinancialMovement;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SpendingSpikeAlert
{
    public static function check(string $month): ?array
    {
        [$yearPart, $monthPart] = explode('-', $month);
        $currentStart = Carbon::createFromDate($yearPart, $monthPart, 1);
        $currentEnd = $currentStart->copy()->endOfMonth();

        // Totais do mês atual
        $movementsThisMonth = FinancialMovement::where('type', 'expense')
            ->whereBetween('date', [$currentStart, $currentEnd])
            ->selectRaw('category_id, SUM(amount) as total')
            ->groupBy('category_id')
            ->get()
            ->keyBy('category_id');

        // Totais dos últimos 6 meses (por mês e por categoria)
        $pastStart = $currentStart->copy()->subMonths(6);
        $pastEnd = $currentStart->copy()->subDay();

        $monthlyTotals = FinancialMovement::where('type', 'expense')
            ->whereBetween('date', [$pastStart, $pastEnd])
            ->selectRaw('category_id, MONTH(date) as month, YEAR(date) as year, SUM(amount) as total')
            ->groupBy('category_id', DB::raw('MONTH(date)'), DB::raw('YEAR(date)'))
            ->get()
            ->groupBy('category_id');

        // Calcular mediana por categoria
        $pastAverages = $monthlyTotals->mapWithKeys(function ($months, $categoryId) {
            $totals = $months->pluck('total')->sort()->values();
            $count = $totals->count();

            if ($count === 0) return [$categoryId => (object)['average' => 0]];

            $median = $count % 2
                ? $totals->get(floor($count / 2))
                : ($totals->get($count / 2 - 1) + $totals->get($count / 2)) / 2;

            return [$categoryId => (object)['average' => $median]];
        });

        $categories = FinancialCategory::all()->keyBy('id');
        $alerts = [];
        foreach ($movementsThisMonth as $categoryId => $current) {
            $avg = $pastAverages[$categoryId]->average ?? null;

            if ($avg && $current->total > $avg) {
                $formattedAvg = number_format($avg, 2, ',', '.');
                $formattedTotal = number_format($current->total, 2, ',', '.');
                $difference = number_format($current->total - $avg, 2, ',', '.');
                $alerts[] = [
                    'type' => 'spending_spike',
                    'category_id' => $categoryId,
                    'message' => "<b>{$categories[$categoryId]->name}</b>: Mês atual: <b>€{$formattedTotal}</b> | Média: <b>€{$formattedAvg}</b> | Diferença: <b>€{$difference}</b>.",
                    'diff' => $current->total - $avg,
                    'severity' =>  $current->total > $avg * 1.5 ? 'danger' : 'warning',
                ];

                // sort alerts by difference in descending order
                usort($alerts, function ($a, $b) {
                    return $b['diff'] <=> $a['diff'];
                });

            }
        }

        return count($alerts) ? $alerts : null;
    }
}
