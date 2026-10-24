<?php

namespace App\Services;

use App\Models\FinancialCategory;
use App\Models\FinancialMovement;
use Carbon\Carbon;

/**
 * Categories x months for the dashboard: last N months up to the selected one,
 * grouped by expense / income, positive amounts, average of the complete months
 * and the forecast (expected_total, stored with the sign of its type).
 */
class CategoryMonthlyService
{
    public const RANGES = [3, 6, 12];

    public static function build(string $month, int $count): array
    {
        $selected = Carbon::createFromFormat('Y-m-d', $month . '-01');
        $months = collect(range($count - 1, 0))->map(function ($back) use ($selected) {
            $date = $selected->copy()->subMonths($back);
            return [
                'key' => $date->format('Y-m'),
                'label' => ucfirst($date->translatedFormat('M/y')),
                'filter' => $date->format('m/Y'),
            ];
        });

        $start = $selected->copy()->subMonths($count - 1)->startOfMonth()->toDateString();
        $end = $selected->copy()->endOfMonth()->toDateString();
        // the average uses complete months since the user started registering:
        // the running month and months before the first movement stay out
        $firstMonth = substr((string) FinancialMovement::min('date'), 0, 7);
        $completeMonths = $months->pluck('key')
            ->reject(fn ($key) => $key >= now()->format('Y-m') || $key < $firstMonth)
            ->values();

        $sums = FinancialMovement::whereNotNull('category_id')
            ->whereBetween('date', [$start, $end])
            ->get(['category_id', 'date', 'amount'])
            ->groupBy('category_id')
            ->map(fn ($items) => $items->groupBy(fn ($item) => substr($item->date, 0, 7))->map->sum('amount'));

        $groups = ['expense' => [], 'income' => []];
        foreach (FinancialCategory::orderBy('name')->get() as $category) {
            $type = $category->type === 'income' ? 'income' : 'expense';
            $sign = $type === 'expense' ? -1 : 1;
            $values = [];
            foreach ($months as $m) {
                $values[$m['key']] = round($sign * (float) ($sums[$category->id][$m['key']] ?? 0), 2);
            }
            $complete = $completeMonths->map(fn ($key) => $values[$key])->all();

            $groups[$type][] = (object) [
                'id' => $category->id,
                'name' => $category->name,
                'type' => $type,
                'values' => $values,
                'average' => $complete ? round(array_sum($complete) / count($complete), 2) : null,
                'forecast' => (float) $category->expected_total != 0 ? abs((float) $category->expected_total) : null,
                'empty' => !array_filter($values) && (float) $category->expected_total == 0,
            ];
        }

        $totals = [];
        foreach ($groups as $type => $rows) {
            foreach ($months as $m) {
                $totals[$type][$m['key']] = round(collect($rows)->sum(fn ($row) => $row->values[$m['key']]), 2);
            }
            // biggest first
            usort($groups[$type], fn ($a, $b) => array_sum($b->values) <=> array_sum($a->values));
        }

        return [
            'firstMonth' => $firstMonth,
            'months' => $months->all(),
            'selected' => $month,
            'groups' => $groups,
            'totals' => $totals,
            'result' => collect($months)->mapWithKeys(fn ($m) => [$m['key'] => round($totals['income'][$m['key']] - $totals['expense'][$m['key']], 2)])->all(),
        ];
    }
}
