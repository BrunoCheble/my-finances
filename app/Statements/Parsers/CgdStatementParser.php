<?php

namespace App\Statements\Parsers;

use App\Statements\ParsedStatement;
use App\Statements\ParsedStatementLine;
use App\Statements\StatementParser;

/**
 * Caixa Geral de Depósitos "Extrato Global".
 *
 * Only the "Depósitos à Ordem" section has movements. Each movement is one
 * tab-separated line: Data Mov. | Data Valor | Descrição | Valor | Saldo Contabilístico.
 * The section starts with "Saldo anterior" and ends with "Saldo contabilístico".
 */
class CgdStatementParser implements StatementParser
{
    private const MOVEMENT = '/^(\d{4}-\d{2}-\d{2})\t(\d{4}-\d{2}-\d{2})\t(.+?)\t(-?[\d.]+,\d{2})\t(-?[\d.]+,\d{2})/u';
    private const AMOUNT = '-?[\d.]+,\d{2}';

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
        return str_contains($text, 'Extrato Global')
            && (str_contains($text, 'CGDIPTPL') || str_contains($text, 'Caixa Geral de Depósitos'));
    }

    public function parse(string $text): ParsedStatement
    {
        $lines = [];
        $opening = null;
        $closing = null;
        $inSection = false;

        foreach (preg_split('/\R/u', $text) as $row) {
            if (!$inSection && preg_match('/Saldo anterior\s+(' . self::AMOUNT . ')/u', $row, $match)) {
                $opening = self::toFloat($match[1]);
                $inSection = true;
                continue;
            }

            if (!$inSection) {
                continue;
            }

            if (preg_match('/^Saldo contabil[íi]stico\s+(' . self::AMOUNT . ')/u', $row, $match)) {
                $closing = self::toFloat($match[1]);
                break;
            }

            if (preg_match(self::MOVEMENT, $row, $match)) {
                $lines[] = new ParsedStatementLine(
                    date: $match[1],
                    description: trim(preg_replace('/\s+/u', ' ', $match[3])),
                    amount: self::toFloat($match[4]),
                    valueDate: $match[2],
                    balance: self::toFloat($match[5]),
                );
            }
        }

        [$periodStart, $periodEnd] = self::period($text);

        return new ParsedStatement(
            bank: $this->bank(),
            lines: $lines,
            periodStart: $periodStart,
            periodEnd: $periodEnd,
            openingBalance: $opening,
            closingBalance: $closing,
            account: preg_match('/CONTA EXTRACTO\s+([\d.]+)/u', $text, $match) ? preg_replace('/\D/', '', $match[1]) : null,
            availableBalance: self::available($text),
        );
    }

    /**
     * "Saldo disponível" right after the demand deposit closing balance.
     */
    private static function available(string $text): ?float
    {
        $closing = preg_match('/^Saldo contabil[íi]stico\s+' . self::AMOUNT . '/mu', $text, $match, PREG_OFFSET_CAPTURE)
            ? $match[0][1] : null;
        if ($closing === null
            || !preg_match('/^Saldo dispon[íi]vel\s+(' . self::AMOUNT . ')/mu', $text, $available, 0, $closing)) {
            return null;
        }
        return self::toFloat($available[1]);
    }

    private static function period(string $text): array
    {
        if (preg_match('/Período\s+(\d{4}-\d{2}-\d{2})\s+a\s+(\d{4}-\d{2}-\d{2})/u', $text, $match)) {
            return [$match[1], $match[2]];
        }
        return [null, null];
    }

    private static function toFloat(string $amount): float
    {
        return (float) str_replace(['.', ','], ['', '.'], $amount);
    }
}
