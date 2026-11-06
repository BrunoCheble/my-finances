<?php

namespace App\Statements;

use App\Enums\FinancialMovementType;
use InvalidArgumentException;

/**
 * Movements pasted as JSON, for any bank (the user picks the wallet): a list of objects, or an
 * object with that list inside (e.g. "transactions"). Each one: date, normalized_type / type
 * (debit, credit, adjustment), merchant_name / description, amount / value. A movement without
 * an amount (0) or a description ('') comes in anyway, to be typed on the review.
 */
class JsonStatementParser
{
    public const BANK = 'json';

    public function parse(string $json): ParsedStatement
    {
        $data = json_decode($json, true);
        if (!is_array($data)) {
            throw new InvalidArgumentException(__('This is not valid JSON.'));
        }

        $items = self::items($data);
        if (!$items) {
            throw new InvalidArgumentException(__('No movements found in the JSON.'));
        }

        $lines = [];
        foreach ($items as $index => $item) {
            $position = $index + 1;
            if (!is_array($item)) {
                throw new InvalidArgumentException(__('Movement :n is not an object.', ['n' => $position]));
            }
            $date = self::date($item['date'] ?? null);
            if (!$date) {
                throw new InvalidArgumentException(__('Movement :n: invalid or missing date.', ['n' => $position]));
            }
            $raw = $item['amount'] ?? $item['value'] ?? null;
            $missing = $raw === null || (is_string($raw) && trim($raw) === '');
            $amount = $missing ? 0.0 : self::parseAmount($raw);
            if ($amount === null) {
                throw new InvalidArgumentException(__('Movement :n: invalid amount.', ['n' => $position]));
            }
            $description = trim((string) (self::text($item['merchant_name'] ?? null) ?: self::text($item['description'] ?? null)));

            // the sign comes from the type: debit leaves, credit enters, an adjustment keeps its own sign
            $type = strtolower(trim((string) ($item['normalized_type'] ?? $item['type'] ?? '')));
            $amount = match ($type) {
                'debit' => -abs($amount),
                'credit' => abs($amount),
                default => $amount,
            };

            $lines[] = new ParsedStatementLine(
                date: $date,
                description: mb_substr($description, 0, 255),
                amount: round($amount, 2),
                // without an amount the sign is unknown: a credit starts as an income
                type: match (true) {
                    $type === 'adjustment' => FinancialMovementType::ADJUSTMENT,
                    $missing && $type === 'credit' => FinancialMovementType::INCOME,
                    default => null,
                },
            );
        }

        $dates = array_map(fn (ParsedStatementLine $line) => $line->date, $lines);
        return new ParsedStatement(bank: self::BANK, lines: $lines, periodStart: min($dates), periodEnd: max($dates));
    }

    /**
     * The list of movements: the JSON itself, or the first list of objects inside it.
     */
    private static function items(array $data): array
    {
        if (array_is_list($data)) {
            return $data;
        }
        foreach ($data as $value) {
            if (is_array($value) && array_is_list($value) && $value && is_array($value[0])) {
                return $value;
            }
        }
        return [];
    }

    private static function text(mixed $value): ?string
    {
        return is_scalar($value) ? (string) $value : null;
    }

    /** "2026-11-05", "2026-11-05T10:22:00Z", "05/11/2026" or "05-11-2026". */
    private static function date(mixed $value): ?string
    {
        $value = trim((string) self::text($value));
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $value, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return "{$m[1]}-{$m[2]}-{$m[3]}";
        }
        if (preg_match('/^(\d{1,2})[\/.-](\d{1,2})[\/.-](\d{4})$/', $value, $m) && checkdate((int) $m[2], (int) $m[1], (int) $m[3])) {
            return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        }
        return null;
    }

    /** A number, or text like "12,40", "-1.234,56", "1,234.56" or "12.40 €". */
    public static function parseAmount(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }
        if (!is_string($value)) {
            return null;
        }
        $text = preg_replace('/[^\d,.\-+]/', '', $value);
        if ($text === '' || !preg_match('/\d/', $text)) {
            return null;
        }
        // the last separator is the decimal one
        $comma = strrpos($text, ',');
        $dot = strrpos($text, '.');
        if ($comma !== false && ($dot === false || $comma > $dot)) {
            $text = str_replace(',', '.', str_replace('.', '', $text));
        } else {
            $text = str_replace(',', '', $text);
        }
        return is_numeric($text) ? (float) $text : null;
    }
}
