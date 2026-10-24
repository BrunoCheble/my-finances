<?php

namespace App\Services;

use App\Models\FinancialCategory;
use App\Models\FinancialMovement;
use Carbon\Carbon;

/**
 * How a category stands in a month, shown right after saving a movement.
 * Same numbers as the dashboard: signed sum of every wallet, positive for the category type.
 */
class CategoryMonthStatusService
{
    public static function for(?int $categoryId, string $date): ?array
    {
        $category = $categoryId ? FinancialCategory::find($categoryId) : null;
        if (!$category) {
            return null;
        }

        $month = Carbon::parse($date);
        $movements = FinancialMovement::where('category_id', $category->id)
            ->whereBetween('date', [$month->copy()->startOfMonth()->toDateString(), $month->copy()->endOfMonth()->toDateString()])
            ->get(['amount']);

        $type = $category->type === 'income' ? 'income' : 'expense';
        $total = round(($type === 'expense' ? -1 : 1) * (float) $movements->sum('amount'), 2);
        $forecast = (float) $category->expected_total != 0 ? abs((float) $category->expected_total) : null;
        $ratio = $forecast ? $total / $forecast : null;

        $status = [
            'id' => $category->id,
            'name' => $category->name,
            'type' => $type,
            'month' => $month->translatedFormat('F'),
            'total' => $total,
            'forecast' => $forecast,
            'percent' => $ratio !== null ? (int) round($ratio * 100) : null,
            'count' => $movements->count(),
            // expense only: what is left of the forecast (negative = over)
            'left' => $forecast && $type === 'expense' ? round($forecast - $total, 2) : null,
            'level' => $type === 'expense' && $ratio !== null
                ? ($ratio >= 1 ? 'over' : ($ratio >= \App\Services\Alerts\BudgetAlerts::WARNING_RATIO ? 'close' : 'ok'))
                : 'ok',
        ];
        $status['text'] = self::sentence($status);

        return $status;
    }

    public static function sentence(array $status): string
    {
        $head = __(':category in :month: :total', [
            'category' => $status['name'],
            'month' => $status['month'],
            'total' => format_currency($status['total']),
        ]);

        if (!$status['forecast']) {
            return $head . ' ' . trans_choice('(:count movement).|(:count movements).', $status['count'], ['count' => $status['count']]);
        }

        $text = $head . ' ' . __('of :forecast forecast (:percent%).', [
            'forecast' => format_currency($status['forecast']),
            'percent' => $status['percent'],
        ]);
        if ($status['left'] !== null) {
            $text .= ' ' . ($status['left'] >= 0
                ? __(':amount left.', ['amount' => format_currency($status['left'])])
                : __(':amount over.', ['amount' => format_currency(-$status['left'])]));
        }
        return $text;
    }
}
