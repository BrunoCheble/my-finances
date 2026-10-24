<?php

namespace App\Services\Statements;

use App\Enums\FinancialMovementType;
use App\Models\FinancialMovement;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Suggests type/category/transfer wallet for a bank description based only on
 * the user's own movements. Descriptions never seen before get no suggestion.
 */
class MovementHistory
{
    /** normalized description => suggestion */
    private Collection $entries;

    public function __construct()
    {
        $this->entries = FinancialMovement::with('destinationMovement', 'originalMovement')
            ->get()
            ->groupBy(fn (FinancialMovement $movement) => self::normalize($movement->description))
            ->filter(fn ($movements, $key) => $key !== '')
            ->map(function (Collection $movements, string $key) {
                $best = $movements
                    ->groupBy(fn ($m) => $m->type . '|' . $m->category_id . '|' . self::counterpartWalletId($m))
                    ->sortByDesc(fn ($group) => $group->count())
                    ->first()
                    ->first();

                return (object) [
                    'description' => $key,
                    'type' => $best->type,
                    'category_id' => $best->category_id,
                    'transfer_wallet_id' => self::counterpartWalletId($best),
                    'count' => $movements->count(),
                    'last_date' => $movements->max('date'),
                ];
            });
    }

    /**
     * @return object|null suggestion with a "match" property (exact|similar)
     */
    public function suggest(string $description): ?object
    {
        $normalized = self::normalize($description);

        if ($exact = $this->entries->get($normalized)) {
            return (object) ((array) $exact + ['match' => 'exact']);
        }

        // a known description contained in the bank text, e.g. "PINGO DOCE" in
        // "PINGO DOCE S JOAO MA"; the longest one wins
        $similar = $this->entries
            ->filter(fn ($entry) => mb_strlen($entry->description) >= 4
                && preg_match('/(^| )' . preg_quote($entry->description, '/') . '( |$)/u', $normalized))
            ->sortByDesc(fn ($entry) => mb_strlen($entry->description))
            ->first();

        return $similar ? (object) ((array) $similar + ['match' => 'similar']) : null;
    }

    /**
     * Uppercase, without accents (bank texts have none), reference numbers and punctuation.
     */
    public static function normalize(?string $description): string
    {
        $text = strtoupper(Str::ascii((string) $description));
        $text = preg_replace('/\d{4,}/u', ' ', $text);
        $text = preg_replace('/[^\p{L}\d ]+/u', ' ', $text);
        return trim(preg_replace('/\s+/u', ' ', $text));
    }

    private static function counterpartWalletId(FinancialMovement $movement): ?int
    {
        if ($movement->type !== FinancialMovementType::TRANSFER) {
            return null;
        }
        return $movement->destinationMovement?->wallet_id ?? $movement->originalMovement?->wallet_id;
    }
}
