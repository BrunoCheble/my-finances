<?php

namespace App\Statements\Parsers;

use App\Statements\ParsedStatement;
use App\Statements\ParsedStatementLine;
use App\Statements\StatementParser;

/**
 * Caixa Geral de Depósitos "Consultar saldos e movimentos à ordem" (Caixadirecta export), a different
 * layout from the "Extrato Global" (CgdStatementParser), same bank code so the account mapping is shared.
 *
 * Movements come newest first, one per line: Data mov. Data-valor Descrição <tab> Montante <tab> Saldo
 * contabilístico após movimento. The closing balance is the "Saldo contabilístico" of the account; the
 * opening one is the oldest line's balance before its amount.
 */
class CgdMovementsQueryParser implements StatementParser
{
    private const AMOUNT = '-?[\d.]+,\d{2}';
    private const MOVEMENT = '/^(\d{2}-\d{2}-\d{4})\s+(\d{2}-\d{2}-\d{4})\s+(.+?)\t(' . self::AMOUNT . ')\t(' . self::AMOUNT . ')\s*$/u';

    public function bank(): string
    {
        return 'cgd';
    }

    public function name(): string
    {
        return 'Caixa Geral de Depósitos';
    }

    public function supports(string $text): bool
    {
        return str_contains($text, 'Consultar saldos e movimentos')
            && (str_contains($text, 'Caixa Geral de Depósitos') || str_contains($text, 'Caixadirecta'));
    }

    public function parse(string $text): ParsedStatement
    {
        $lines = [];
        foreach (preg_split('/\R/u', $text) as $row) {
            if (preg_match(self::MOVEMENT, $row, $match)) {
                $lines[] = new ParsedStatementLine(
                    date: self::date($match[1]),
                    description: trim(preg_replace('/\s+/u', ' ', $match[3])),
                    amount: self::toFloat($match[4]),
                    valueDate: self::date($match[2]),
                    balance: self::toFloat($match[5]),
                );
            }
        }
        // oldest first, like the other statements
        $lines = array_reverse($lines);

        $oldest = $lines[0] ?? null;
        $closing = preg_match('/Saldo contabil[íi]stico\s*(' . self::AMOUNT . ')\s*EUR/u', $text, $match)
            ? self::toFloat($match[1])
            : (end($lines) ?: null)?->balance;

        $dates = array_map(fn (ParsedStatementLine $line) => $line->date, $lines);
        $period = preg_match('/Intervalo de a\s*(\d{2}-\d{2}-\d{4})\s*(\d{2}-\d{2}-\d{4})/u', $text, $match)
            ? [self::date($match[1]), self::date($match[2])]
            : [$dates ? min($dates) : null, $dates ? max($dates) : null];

        return new ParsedStatement(
            bank: $this->bank(),
            lines: $lines,
            periodStart: $period[0],
            periodEnd: $period[1],
            openingBalance: $oldest ? round($oldest->balance - $oldest->amount, 2) : $closing,
            closingBalance: $closing,
            account: preg_match('/^Conta\s+(\d+)\s+-/mu', $text, $match) ? $match[1] : null,
            availableBalance: preg_match('/Saldo dispon[íi]vel\s*(' . self::AMOUNT . ')/u', $text, $match) ? self::toFloat($match[1]) : null,
        );
    }

    /** "07-11-2026" -> "2026-11-07" */
    private static function date(string $date): string
    {
        [$day, $month, $year] = explode('-', $date);
        return "$year-$month-$day";
    }

    private static function toFloat(string $amount): float
    {
        return (float) str_replace(['.', ','], ['', '.'], $amount);
    }
}
