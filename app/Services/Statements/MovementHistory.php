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

        // a bank transfer may go to a third party (an expense), so it only
        // reuses an exactly equal description
        if (self::isTransferText($description)) {
            return null;
        }

        // a known description contained in the bank text, e.g. "PINGO DOCE" in
        // "PINGO DOCE S JOAO MA"; the longest one wins. Never suggests an
        // internal transfer by similarity.
        $similar = $this->entries
            ->filter(fn ($entry) => $entry->type !== FinancialMovementType::TRANSFER
                && mb_strlen($entry->description) >= 4
                && preg_match('/(^| )' . preg_quote($entry->description, '/') . '( |$)/u', $normalized))
            ->sortByDesc(fn ($entry) => mb_strlen($entry->description))
            ->first();

        return $similar ? (object) ((array) $similar + ['match' => 'similar']) : null;
    }

    /**
     * Generic bank words that say nothing about the merchant. Transfer words and
     * prepositions are included so the recipient comes first ("TRF PARA JOAO").
     */
    private const STOPWORDS = [
        'COMPRA', 'COMPRAS', 'C', 'DEB', 'CRED', 'PAG', 'PAGAMENTO', 'MB', 'DD',
        'TRF', 'TFI', 'TRANSF', 'TRANSFERENCIA', 'SEPA', 'PARA', 'DE', 'DO', 'DA', 'DOS', 'DAS',
    ];

    private const TRANSFER_WORDS = ['TRF', 'TFI', 'TRANSF', 'TRANSFERENCIA', 'SEPA', 'MBWAY'];

    /**
     * Whether the bank text describes a transfer, to a third party or not.
     */
    public static function isTransferText(?string $description): bool
    {
        $words = preg_split('/[^A-Z0-9]+/', strtoupper(Str::ascii((string) $description)), -1, PREG_SPLIT_NO_EMPTY);
        $joined = ' ' . implode(' ', $words) . ' ';

        return (bool) array_intersect($words, self::TRANSFER_WORDS) || str_contains($joined, ' MB WAY ');
    }

    /**
     * Words that identify the merchant: no purchase codes (any word with a digit),
     * no punctuation and no generic bank words.
     */
    public static function keywords(?string $description): array
    {
        $words = preg_split('/[^A-Z0-9]+/', strtoupper(Str::ascii((string) $description)), -1, PREG_SPLIT_NO_EMPTY);

        return array_values(array_filter(
            $words,
            fn ($word) => !preg_match('/\d/', $word) && !in_array($word, self::STOPWORDS, true)
        ));
    }

    /**
     * Same merchant when both start with the same two keywords
     * (or the same single keyword of 4+ letters when one has only one).
     */
    public static function sameMerchant(array $a, array $b): bool
    {
        $needed = min(2, count($a), count($b));
        if ($needed === 0 || array_slice($a, 0, $needed) !== array_slice($b, 0, $needed)) {
            return false;
        }
        return $needed === 2 || strlen($a[0]) >= 4;
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
