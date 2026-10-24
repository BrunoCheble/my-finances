<?php

namespace App\Services\Alerts;

/**
 * Expense categories close to (>= 80%) or above (>= 100%) their forecast.
 */
class BudgetAlerts
{
    public const WARNING_RATIO = 0.8;

    public static function check(AlertContext $context): array
    {
        $alerts = [];
        foreach (AlertContext::spendingByCategory($context->movements) as $categoryId => $spent) {
            $category = $context->categories->get($categoryId);
            $budget = $category ? abs((float) $category->expected_total) : 0;
            if (!$category || $category->type !== 'expense' || $budget <= 0 || $spent <= 0) {
                continue;
            }

            $ratio = $spent / $budget;
            if ($ratio < self::WARNING_RATIO) {
                continue;
            }

            $over = $ratio >= 1;
            $alerts[] = AlertContext::alert(
                "budget-{$categoryId}",
                $over ? 'critical' : 'warning',
                $over ? 'circle-exclamation' : 'gauge-high',
                $over
                    ? __(':category above forecast', ['category' => $category->name])
                    : __(':category close to the forecast', ['category' => $category->name]),
                __(':spent of :budget forecast (:percent%).', [
                    'spent' => format_currency($spent),
                    'budget' => format_currency($budget),
                    'percent' => round($ratio * 100),
                ]) . ($over ? ' ' . __(':amount over.', ['amount' => format_currency($spent - $budget)]) : ''),
                __('See movements'),
                $context->movementsLink($categoryId),
                $ratio,
            );
        }
        return $alerts;
    }
}
