<?php

namespace App\Services;

use App\Models\FinancialMovement;
use App\Services\Statements\MovementHistory;

/**
 * Autocomplete for the movement description: descriptions already used,
 * grouped when they only differ by purchase codes, with their last type,
 * category and amount.
 */
class DescriptionSuggestionsService
{
    public const LIMIT = 8;
    private const SCAN = 400;

    public static function search(string $term, int $limit = self::LIMIT): array
    {
        $term = trim($term);
        if (mb_strlen($term) < 2) {
            return [];
        }

        // '!' as escape character: same behaviour on MySQL and SQLite
        $like = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term) . '%';
        $movements = FinancialMovement::with('category:id,name')
            ->whereNull('original_movement_id')
            ->whereRaw("description LIKE ? ESCAPE '!'", [$like])
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->limit(self::SCAN)
            ->get(['id', 'description', 'type', 'category_id', 'amount', 'date']);

        $needle = MovementHistory::normalize($term);

        return $movements
            ->groupBy(fn ($movement) => MovementHistory::normalize($movement->description) ?: $movement->description)
            ->map(function ($group, $key) use ($needle) {
                $last = $group->first();
                return [
                    'description' => $last->description,
                    'type' => $last->type,
                    'category_id' => $last->category_id ? (string) $last->category_id : null,
                    'category' => $last->category?->name,
                    'amount' => (float) $last->amount,
                    'date' => $last->date,
                    'uses' => $group->count(),
                    'prefix' => $needle !== '' && str_starts_with($key, $needle),
                ];
            })
            ->sortBy([
                fn ($a, $b) => $b['prefix'] <=> $a['prefix'],
                fn ($a, $b) => $b['uses'] <=> $a['uses'],
                fn ($a, $b) => strcmp($b['date'], $a['date']),
            ])
            ->take($limit)
            ->map(fn ($suggestion) => collect($suggestion)->except('prefix')->all())
            ->values()
            ->all();
    }
}
