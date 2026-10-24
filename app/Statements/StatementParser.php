<?php

namespace App\Statements;

interface StatementParser
{
    /**
     * Short identifier stored with the import (e.g. "cgd").
     */
    public function bank(): string;

    /**
     * Whether this parser recognizes the statement layout.
     */
    public function supports(string $text): bool;

    public function parse(string $text): ParsedStatement;
}
