<?php

namespace App\Services\Alerts;

use App\Models\FinancialCategory;
use App\Models\FinancialMovement;
use Carbon\Carbon;

class CategoryImbalanceAlert
{
    public static function check(string $month): ?array
    {
        [$yearPart, $monthPart] = explode('-', $month);
        $start = Carbon::createFromDate($yearPart, $monthPart, 1);
        $end = $start->copy()->endOfMonth();

        // Buscar total por categoria no mês
        $movementsThisMonth = FinancialMovement::where('type', 'expense')
            ->whereBetween('date', [$start, $end])
            ->selectRaw('category_id, SUM(amount) as total')
            ->groupBy('category_id')
            ->get()
            ->keyBy('category_id');

        $totalGeneral = $movementsThisMonth->sum('total');

        if ($totalGeneral == 0) {
            return null; // sem despesas no mês
        }

        $categories = FinancialCategory::all()->keyBy('id');
        $alerts = [];

        foreach ($movementsThisMonth as $categoryId => $data) {
            $percent = ($data->total / $totalGeneral) * 100;

            if ($percent >= 30) {
                $formattedTotal = number_format($data->total, 2, ',', '.');
                $formattedPercent = number_format($percent, 1, ',', '');

                $alerts[] = [
                    'type' => 'category_imbalance',
                    'category_id' => $categoryId,
                    'message' => "<b>{$categories[$categoryId]->name}</b> representa <b>{$formattedPercent}%</b> (€{$formattedTotal}) dos gastos do mês.",
                    'percent' => $percent,
                    'severity' => $percent > 40 ? 'danger' : 'warning',
                ];
            }
        }

        // Ordenar por percentual decrescente
        usort($alerts, fn($a, $b) => $b['percent'] <=> $a['percent']);

        return count($alerts) ? $alerts : null;
    }
}
