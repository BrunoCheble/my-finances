<?php

namespace App\Services;

class GetFinancialCategoryService
{
    public static function execute($movements, $categories) {
        foreach ($movements as $movement) {
            if (!$movement->category_id) continue;
            foreach ($categories as &$category) {
                if ($movement->category_id != $category->id) continue;

                if ($movement->isDebit) {
                    $category->total_expense += $movement->amount;
                }
                else $category->total_income += $movement->amount;
            }
        }

        return $categories;
    }
}
