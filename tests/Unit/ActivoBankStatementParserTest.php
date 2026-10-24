<?php

use App\Statements\Parsers\ActivoBankStatementParser;
use App\Statements\Parsers\CgdStatementParser;

function activoBankFixture(): string
{
    return file_get_contents(__DIR__ . '/../Fixtures/activobank-statement.txt');
}

it('recognizes the ActivoBank layout only', function () {
    expect((new ActivoBankStatementParser())->supports(activoBankFixture()))->toBeTrue()
        ->and((new CgdStatementParser())->supports(activoBankFixture()))->toBeFalse();
});

it('reads two-line movements and reconciles', function () {
    $statement = (new ActivoBankStatementParser())->parse(activoBankFixture());

    expect($statement->lines)->toHaveCount(3)
        ->and($statement->periodStart)->toBe('2026-12-15')
        ->and($statement->periodEnd)->toBe('2027-01-14')
        ->and($statement->openingBalance)->toBe(500.0)
        ->and($statement->closingBalance)->toBe(1080.5)
        ->and($statement->reconciles())->toBeTrue()
        ->and($statement->lines[0]->description)->toBe('COMPRA 1234 SUPERMERCADO FICTICIO LISBOA');
});

it('takes the sign from the balance change and the year from the period', function () {
    $lines = (new ActivoBankStatementParser())->parse(activoBankFixture())->lines;

    expect($lines[0]->amount)->toBe(-20.5)
        ->and($lines[1]->amount)->toBe(1200.0)
        ->and($lines[2]->amount)->toBe(-599.0)
        ->and($lines[0]->date)->toBe('2026-12-20')
        ->and($lines[0]->valueDate)->toBe('2026-12-21')
        // January belongs to the next year of a December-January statement
        ->and($lines[2]->date)->toBe('2027-01-05');
});

it('reads the account number', function () {
    expect((new ActivoBankStatementParser())->parse(activoBankFixture())->account)->toBe('00000000000');
});

it('reads the available balance', function () {
    expect((new ActivoBankStatementParser())->parse(activoBankFixture())->availableBalance)->toBe(1080.5)
        ->and((new CgdStatementParser())->parse(file_get_contents(__DIR__ . '/../Fixtures/cgd-statement.txt'))->availableBalance)->toBe(2237.45);
});
