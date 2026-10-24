<?php

namespace App\Services\Alerts;

use Carbon\Carbon;

/**
 * Categories spending well above their usual (median of the last 6 months).
 * In the current month the comparison uses the pace so far, not the partial total.
 */
class SpendingAlerts
{
    public const MIN_HISTORY_MONTHS = 3;
    public const MIN_RATIO = 1.2;
    public const MIN_DIFFERENCE = 20;
    public const MIN_DAYS_FOR_PACE = 7;

    public static function check(AlertContext $context, array $skipCategories = []): array
    {
        if ($context->isCurrentMonth() && now()->day < self::MIN_DAYS_FOR_PACE) {
            return [];
        }

        // monthly spending per category in the history window
        $history = $context->history
            ->groupBy(fn ($movement) => Carbon::parse($movement->date)->format('Y-m'))
            ->map(fn ($movements) => AlertContext::spendingByCategory($movements));

        $alerts = [];
        foreach (AlertContext::spendingByCategory($context->movements) as $categoryId => $spent) {
            $category = $context->categories->get($categoryId);
            if (!$category || $category->type !== 'expense' || in_array($categoryId, $skipCategories)) {
                continue;
            }

            $past = $history->map(fn ($month) => $month->get($categoryId))->filter(fn ($value) => $value > 0)->values();
            if ($past->count() < self::MIN_HISTORY_MONTHS) {
                continue;
            }

            $usual = (float) $past->median();
            $value = $context->isCurrentMonth() ? $spent / $context->progress : $spent;
            if ($usual <= 0 || $value < $usual * self::MIN_RATIO || $value - $usual < self::MIN_DIFFERENCE) {
                continue;
            }

            $ratio = $value / $usual;
            $message = $context->isCurrentMonth()
                ? __('At the current pace it should close around :value, while it is usually :usual.', [
                    'value' => format_currency($value), 'usual' => format_currency($usual)])
                : __(':value this month, while it is usually :usual (+:percent%).', [
                    'value' => format_currency($value), 'usual' => format_currency($usual), 'percent' => round(($ratio - 1) * 100)]);

            $alerts[] = AlertContext::alert(
                "spending-{$categoryId}",
                $ratio >= 1.5 ? 'warning' : 'info',
                'arrow-trend-up',
                __(':category above usual', ['category' => $category->name]),
                $message,
                __('See movements'),
                $context->movementsLink($categoryId),
                $ratio,
            );
        }
        return $alerts;
    }
}
