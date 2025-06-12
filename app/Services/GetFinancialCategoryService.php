<?php

namespace App\Services;

use Illuminate\Support\Collection;

class GetFinancialCategoryService
{
    public static function execute(Collection $movements, Collection $categories)
    {
        $groupedMovements = $movements->groupBy('category_id');

        foreach ($categories as $category) {
            $categoryMovements = $groupedMovements->get($category->id, collect());

            $category->total_expense = $categoryMovements
                ->where('isDebit', true)
                ->sum('amount');

            $category->total_income = $categoryMovements
                ->where('isDebit', false)
                ->sum('amount');
        }

        return $categories;
    }
}
