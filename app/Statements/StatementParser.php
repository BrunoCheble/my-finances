<?php

namespace App\Statements;

interface StatementParser
{
    /**
     * Short identifier stored with the import (e.g. "cgd").
     */
    public function bank(): string;

    /**
     * Bank name shown to the user.
     */
    public function name(): string;

    /**
     * Whether this parser recognizes the statement layout.
     */
    public function supports(string $text): bool;

    public function parse(string $text): ParsedStatement;
}
