<?php

namespace App\Statements\Parsers;

use App\Statements\ParsedStatement;
use App\Statements\ParsedStatementLine;
use App\Statements\StatementParser;

/**
 * ActivoBank "Extrato Combinado" (usually a protected PDF).
 *
 * Each movement takes two lines: "M.DD M.DD" (Data Lanç. / Data Valor, no year)
 * followed by "DESCRITIVO<tab>AMOUNT BALANCE". Debit and credit share the same
 * text position and have no sign, so the sign comes from the balance change.
 * The account section goes from "SALDO INICIAL" to "SALDO FINAL".
 */
class ActivoBankStatementParser implements StatementParser
{
    private const AMOUNT = '-?\d{1,3}(?: \d{3})*\.\d{2}';

    public function bank(): string
    {
        return 'activobank';
    }

    public function name(): string
    {
        return 'ActivoBank';
    }

    public function supports(string $text): bool
    {
        return str_contains($text, 'ActivoBank') && str_contains($text, 'EXTRATO COMBINADO');
    }

    public function parse(string $text): ParsedStatement
    {
        [$periodStart, $periodEnd] = self::period($text);

        $lines = [];
        $opening = null;
        $closing = null;
        $balance = null;
        $dates = null;

        foreach (preg_split('/\R/u', $text) as $row) {
            if ($opening === null) {
                if (preg_match('/^SALDO INICIAL\s+(' . self::AMOUNT . ')/u', $row, $match)) {
                    $opening = $balance = self::toFloat($match[1]);
                }
                continue;
            }

            if (preg_match('/^SALDO FINAL\s+(' . self::AMOUNT . ')/u', $row, $match)) {
                $closing = self::toFloat($match[1]);
                break;
            }

            if (preg_match('/^(\d{1,2})\.(\d{2}) (\d{1,2})\.(\d{2})\s*$/', $row, $match)) {
                $dates = [
                    self::date($match[1], $match[2], $periodStart, $periodEnd),
                    self::date($match[3], $match[4], $periodStart, $periodEnd),
                ];
                continue;
            }

            if ($dates && preg_match('/^(.+?)\t(' . self::AMOUNT . ') (' . self::AMOUNT . ')\s*$/u', $row, $match)) {
                $amount = self::toFloat($match[2]);
                $newBalance = self::toFloat($match[3]);
                if ($newBalance < $balance) {
                    $amount = -$amount;
                }

                $lines[] = new ParsedStatementLine(
                    date: $dates[0],
                    description: trim(preg_replace('/\s+/u', ' ', $match[1])),
                    amount: $amount,
                    valueDate: $dates[1],
                    balance: $newBalance,
                );
                $balance = $newBalance;
                $dates = null;
            }
        }

        return new ParsedStatement(
            bank: $this->bank(),
            lines: $lines,
            periodStart: $periodStart,
            periodEnd: $periodEnd,
            openingBalance: $opening,
            closingBalance: $closing,
            account: preg_match('/DEPOSITO A ORDEM:\s*(\d+)/', $text, $match) ? $match[1] : null,
            availableBalance: preg_match('/^SALDO DISPONIVEL\s+(' . self::AMOUNT . ')/mu', $text, $match) ? self::toFloat($match[1]) : null,
        );
    }

    private static function period(string $text): array
    {
        if (preg_match('/(\d{4})\/(\d{2})\/(\d{2})\s+A\s+(\d{4})\/(\d{2})\/(\d{2})/', $text, $m)) {
            return ["$m[1]-$m[2]-$m[3]", "$m[4]-$m[5]-$m[6]"];
        }
        return [null, null];
    }

    /**
     * Dates have no year: take it from the period (a December-January statement
     * spans two years).
     */
    private static function date(string $month, string $day, ?string $periodStart, ?string $periodEnd): string
    {
        $start = $periodStart ? strtotime($periodStart) : time();
        $end = $periodEnd ? strtotime($periodEnd) : $start;
        $year = (int) $month < (int) date('n', $start) ? date('Y', $end) : date('Y', $start);

        return sprintf('%s-%02d-%02d', $year, $month, $day);
    }

    private static function toFloat(string $amount): float
    {
        return (float) str_replace(' ', '', $amount);
    }
}
