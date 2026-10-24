<?php

namespace App\Services;

use App\Models\FinancialCategory;
use App\Models\FinancialMovement;
use Illuminate\Support\Collection;

/**
 * Active categories for the searchable pickers, with how often each one is used.
 */
class CategoryOptionsService
{
    public static function forPicker(): Collection
    {
        $uses = FinancialMovement::whereNotNull('category_id')
            ->selectRaw('category_id, count(*) as total')
            ->groupBy('category_id')
            ->pluck('total', 'category_id');

        return FinancialCategory::active()->orderBy('name')->get(['id', 'name', 'type'])
            ->map(fn ($category) => [
                'id' => (string) $category->id,
                'name' => $category->name,
                'type' => $category->type,
                'uses' => (int) ($uses[$category->id] ?? 0),
            ])->values();
    }
}
