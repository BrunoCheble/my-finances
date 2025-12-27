<?php

namespace App\Services;

use App\Models\FinancialCategory;
use Illuminate\Support\Facades\DB;

class ExpectedTotalService
{
    public function bulkUpdate(array $expectedValues, int $userId): void
    {
        DB::transaction(function () use ($expectedValues, $userId) {
            foreach ($expectedValues as $categoryId => $value) {
                FinancialCategory::where('id', $categoryId)
                    ->where('user_id', $userId)
                    ->where('expected_total', '!=', $value)
                    ->update([
                        'expected_total' => $value,
                    ]);
            }
        });
    }
}
