<?php

namespace App\Services\Alerts;

use App\Services\Statements\MovementHistory;

/**
 * Single purchases far above the usual of their category, and possible duplicates.
 */
class UnusualMovementAlerts
{
    public const OUTLIER_RATIO = 3;
    public const OUTLIER_MIN_AMOUNT = 50;
    public const OUTLIER_MIN_SAMPLES = 5;
    public const DUPLICATE_DAYS = 2;

    public static function check(AlertContext $context): array
    {
        return array_merge(self::outliers($context), self::duplicates($context));
    }

    private static function outliers(AlertContext $context): array
    {
        $usualByCategory = $context->history
            ->where('type', 'expense')->whereNotNull('category_id')
            ->groupBy('category_id')
            ->map(fn ($items) => $items->count() >= self::OUTLIER_MIN_SAMPLES ? (float) $items->map(fn ($m) => abs($m->amount))->median() : null)
            ->filter();

        $alerts = [];
        foreach ($context->movements->where('type', 'expense')->whereNotNull('category_id') as $movement) {
            $usual = $usualByCategory->get($movement->category_id);
            $amount = abs((float) $movement->amount);
            if (!$usual || $amount < self::OUTLIER_MIN_AMOUNT || $amount < $usual * self::OUTLIER_RATIO) {
                continue;
            }
            $alerts[] = AlertContext::alert(
                "outlier-{$movement->id}",
                'warning',
                'magnifying-glass-dollar',
                __('Unusual purchase: :description', ['description' => $movement->description]),
                __(':amount in :category on :date, about :times× the usual (≈ :usual).', [
                    'amount' => format_currency($amount),
                    'category' => $movement->category?->name,
                    'date' => date_format_custom($movement->date),
                    'times' => round($amount / $usual),
                    'usual' => format_currency($usual),
                ]),
                __('See movement'),
                $context->movementsLink(search: $movement->description),
                $amount / $usual,
            );
        }
        return $alerts;
    }

    private static function duplicates(AlertContext $context): array
    {
        $alerts = [];
        $candidates = $context->movements
            ->whereIn('type', ['expense', 'income', 'refund', 'discount'])
            ->sortBy('date')->values();

        $seen = [];
        foreach ($candidates as $i => $movement) {
            foreach ($candidates->slice($i + 1) as $other) {
                $days = abs(strtotime($other->date) - strtotime($movement->date)) / 86400;
                if ($days > self::DUPLICATE_DAYS) {
                    break;
                }
                if ($other->wallet_id !== $movement->wallet_id
                    || round((float) $other->amount, 2) !== round((float) $movement->amount, 2)
                    || MovementHistory::normalize($other->description) !== MovementHistory::normalize($movement->description)
                    || isset($seen[$other->id])) {
                    continue;
                }
                $seen[$other->id] = true;
                $alerts[] = AlertContext::alert(
                    "duplicate-{$movement->id}-{$other->id}",
                    'warning',
                    'clone',
                    __('Possible duplicate: :description', ['description' => $movement->description]),
                    __(':amount on :first and again on :second in the same wallet.', [
                        'amount' => format_currency(abs((float) $movement->amount)),
                        'first' => date_format_custom($movement->date),
                        'second' => date_format_custom($other->date),
                    ]),
                    __('Check movements'),
                    $context->movementsLink(search: $movement->description),
                    2,
                );
            }
        }
        return $alerts;
    }
}
