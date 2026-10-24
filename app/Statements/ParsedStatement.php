<?php

namespace App\Statements;

/**
 * Bank-agnostic result of parsing a statement.
 */
class ParsedStatement
{
    /**
     * @param ParsedStatementLine[] $lines
     */
    public function __construct(
        public readonly string $bank,
        public readonly array $lines,
        public readonly ?string $periodStart = null,
        public readonly ?string $periodEnd = null,
        public readonly ?float $openingBalance = null,
        public readonly ?float $closingBalance = null,
        public readonly ?string $account = null,
    ) {
    }

    public function accountLast4(): ?string
    {
        return $this->account ? substr($this->account, -4) : null;
    }

    /**
     * Whether opening balance + all amounts equals the closing balance.
     * Null when the statement doesn't provide both balances.
     */
    public function reconciles(): ?bool
    {
        if ($this->openingBalance === null || $this->closingBalance === null) {
            return null;
        }

        $total = array_sum(array_map(fn (ParsedStatementLine $line) => $line->amount, $this->lines));

        return round($this->openingBalance + $total, 2) === round($this->closingBalance, 2);
    }
}
