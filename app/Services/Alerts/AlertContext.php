<?php

namespace App\Services\Alerts;

use App\Enums\FinancialMovementType;
use App\Models\FinancialCategory;
use App\Models\FinancialMovement;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Data shared by the alert checks of a month, loaded once.
 */
class AlertContext
{
    public const SPENDING_TYPES = [FinancialMovementType::EXPENSE, FinancialMovementType::REFUND];
    public const HISTORY_MONTHS = 6;

    public Carbon $start;
    public Carbon $end;
    public Collection $movements;
    public Collection $history;
    public Collection $categories;
    /** share of the month already gone (1 for past months) */
    public float $progress;

    public function __construct(public string $month)
    {
        $this->start = Carbon::createFromFormat('Y-m-d', $month . '-01')->startOfDay();
        $this->end = $this->start->copy()->endOfMonth();

        $today = now();
        $this->progress = $today->between($this->start, $this->end)
            ? $today->day / $this->end->day
            : 1.0;

        $this->movements = FinancialMovement::with('category')
            ->whereBetween('date', [$this->start->toDateString(), $this->end->toDateString()])
            ->get();
        $this->history = FinancialMovement::whereBetween('date', [
            $this->start->copy()->subMonths(self::HISTORY_MONTHS)->toDateString(),
            $this->start->copy()->subDay()->toDateString(),
        ])->get();
        $this->categories = FinancialCategory::all()->keyBy('id');
    }

    public function isCurrentMonth(): bool
    {
        return $this->progress < 1;
    }

    /**
     * Net spending per category (expenses minus refunds, positive number).
     */
    public static function spendingByCategory(Collection $movements): Collection
    {
        return $movements->whereIn('type', self::SPENDING_TYPES)
            ->whereNotNull('category_id')
            ->groupBy('category_id')
            ->map(fn ($items) => round(-$items->sum('amount'), 2));
    }

    public function movementsLink(?int $categoryId = null, ?string $search = null): string
    {
        return route('financial-movements.index', array_filter([
            'attribute' => $categoryId ? 'category_id' : ($search ? 'description' : null),
            'search' => $categoryId ?? $search,
            'date_from' => $this->start->toDateString(),
            'date_to' => $this->end->toDateString(),
        ]));
    }

    public static function alert(string $key, string $level, string $icon, string $title, string $message, ?string $actionLabel = null, ?string $actionUrl = null, float $weight = 0): array
    {
        return [
            'key' => $key,
            'level' => $level,
            'icon' => $icon,
            'title' => $title,
            'message' => $message,
            'action' => $actionUrl ? ['label' => $actionLabel, 'url' => $actionUrl] : null,
            'weight' => $weight,
        ];
    }
}
