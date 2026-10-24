<?php

namespace App\Services\Alerts;

use App\Services\ReconciliationService;

/**
 * Confirmed months whose balance changed afterwards (any month, not only the selected one).
 */
class ReconciliationAlerts
{
    public static function check(AlertContext $context): array
    {
        return ReconciliationService::diverged()->map(function ($balance) {
            $month = \Carbon\Carbon::parse($balance->start_date)->translatedFormat('F Y');
            return AlertContext::alert(
                "reconciliation-{$balance->id}",
                'critical',
                'lock-open',
                __(':wallet changed in :month, a month you confirmed', ['wallet' => $balance->wallet?->name, 'month' => $month]),
                __('Confirmed :confirmed, now :current (:difference).', [
                    'confirmed' => format_currency($balance->reconciled_balance),
                    'current' => format_currency($balance->calculated_balance),
                    'difference' => ($balance->reconciliation_difference > 0 ? '+' : '') . format_currency($balance->reconciliation_difference),
                ]),
                __('Review'),
                route('financial-balances.index', ['year' => substr($balance->start_date, 0, 4)]) . '#month-' . substr($balance->start_date, 0, 7),
                abs($balance->reconciliation_difference),
            );
        })->values()->all();
    }
}
