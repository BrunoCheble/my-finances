<?php

namespace App\Statements;

class ParsedStatementLine
{
    public function __construct(
        public readonly string $date,
        public readonly string $description,
        public readonly float $amount,
        public readonly ?string $valueDate = null,
        public readonly ?float $balance = null,
    ) {
    }
}
