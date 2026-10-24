<?php

use App\Statements\Parsers\CgdStatementParser;

function cgdFixture(): string
{
    return file_get_contents(__DIR__ . '/../Fixtures/cgd-statement.txt');
}

it('recognizes the CGD layout only', function () {
    $parser = new CgdStatementParser();

    expect($parser->supports(cgdFixture()))->toBeTrue()
        ->and($parser->supports("Extrato\nOutro banco qualquer"))->toBeFalse();
});

it('reads only the movements of the demand deposit section', function () {
    $statement = (new CgdStatementParser())->parse(cgdFixture());

    expect($statement->lines)->toHaveCount(4)
        ->and($statement->periodStart)->toBe('2026-08-01')
        ->and($statement->periodEnd)->toBe('2026-08-31')
        ->and($statement->openingBalance)->toBe(1000.0)
        ->and($statement->closingBalance)->toBe(2237.45)
        ->and($statement->reconciles())->toBeTrue();
});

it('parses dates, amounts and descriptions', function () {
    $lines = (new CgdStatementParser())->parse(cgdFixture())->lines;

    expect($lines[1]->date)->toBe('2026-08-05')
        ->and($lines[1]->valueDate)->toBe('2026-08-06')
        ->and($lines[1]->amount)->toBe(1500.0)
        ->and($lines[0]->amount)->toBe(-50.25)
        // page footer "1 of 3" glued to the last line of the page is ignored
        ->and($lines[2]->balance)->toBe(2249.75)
        ->and($lines[3]->description)->toBe('UBER TRIP 9876543210');
});
