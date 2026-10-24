<?php

namespace App\Statements;

use App\Statements\Parsers\ActivoBankStatementParser;
use App\Statements\Parsers\CgdStatementParser;

class StatementParserRegistry
{
    /**
     * Add a new bank by creating a parser and listing it here.
     */
    private const PARSERS = [
        CgdStatementParser::class,
        ActivoBankStatementParser::class,
    ];

    public function detect(string $text): ?StatementParser
    {
        foreach (self::PARSERS as $class) {
            $parser = app($class);
            if ($parser->supports($text)) {
                return $parser;
            }
        }
        return null;
    }

    /**
     * Supported banks: code => name.
     */
    public function names(): array
    {
        $names = [];
        foreach (self::PARSERS as $class) {
            $parser = app($class);
            $names[$parser->bank()] = $parser->name();
        }
        return $names;
    }

    public function name(string $bank): string
    {
        foreach (self::PARSERS as $class) {
            $parser = app($class);
            if ($parser->bank() === $bank) {
                return $parser->name();
            }
        }
        return $bank;
    }
}
