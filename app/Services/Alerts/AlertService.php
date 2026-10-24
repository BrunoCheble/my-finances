<?php

namespace App\Services\Alerts;

/**
 * Dashboard alerts of a month, most important first. Messages are plain text.
 */
class AlertService
{
    public const LEVELS = ['critical' => 0, 'warning' => 1, 'info' => 2];

    public static function for(string $month): array
    {
        $context = new AlertContext($month);

        $budget = BudgetAlerts::check($context);
        // a category already flagged by its forecast doesn't need a second alert
        $flagged = array_map(fn ($alert) => (int) substr($alert['key'], strlen('budget-')), $budget);

        return collect(array_merge(
            $budget,
            SpendingAlerts::check($context, $flagged),
            UnusualMovementAlerts::check($context),
            PendingAlerts::check($context),
        ))
            ->sortBy([
                fn ($a, $b) => self::LEVELS[$a['level']] <=> self::LEVELS[$b['level']],
                fn ($a, $b) => $b['weight'] <=> $a['weight'],
            ])
            ->values()
            ->all();
    }
}
