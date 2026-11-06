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
        // a movement type the source already knows (e.g. an adjustment in a pasted JSON)
        public readonly ?string $type = null,
    ) {
    }
}
