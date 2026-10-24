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

it('matches merchants ignoring purchase codes and generic bank words', function () {
    $keywords = fn ($d) => App\Services\Statements\MovementHistory::keywords($d);
    $same = fn ($a, $b) => App\Services\Statements\MovementHistory::sameMerchant($keywords($a), $keywords($b));

    expect($keywords('COMPRAS C.DEB UBER *TRIP 8K2LQ 1234567890'))->toBe(['UBER', 'TRIP'])
        ->and($same('UBER *TRIP 8K2LQ', 'UBER TRIP 12'))->toBeTrue()
        ->and($same('PINGO DOCE S JOAO MA 123', 'COMPRA 4412 PINGO DOCE LISBOA'))->toBeTrue()
        ->and($same('FARMACIA 77', 'FARMACIA CENTRAL'))->toBeTrue()
        ->and($same('UBER TRIP', 'UBER EATS'))->toBeFalse()
        ->and($same('TRF PARA POUPANCA', 'TRF PARA JOAO'))->toBeFalse()
        ->and($same('TFI INES FILIPA S', 'TFI CATARINA FILIPA L'))->toBeFalse()
        ->and($same('TRF PARA POUPANCA 001', 'TRANSFERENCIA PARA POUPANCA'))->toBeTrue()
        ->and($same('1234 5678', '1234 5678'))->toBeFalse();
});

it('recognizes bank transfer descriptions', function () {
    $isTransfer = fn ($d) => App\Services\Statements\MovementHistory::isTransferText($d);

    expect($isTransfer('TRF PARA JOAO'))->toBeTrue()
        ->and($isTransfer('TFI CATARINA FILIPA L'))->toBeTrue()
        ->and($isTransfer('TRF CRED SEPA+ DE EMPRESA'))->toBeTrue()
        ->and($isTransfer('Transferência para poupança'))->toBeTrue()
        ->and($isTransfer('MB WAY 912345678'))->toBeTrue()
        ->and($isTransfer('PINGO DOCE S JOAO MA'))->toBeFalse()
        ->and($isTransfer('UBER TRIP'))->toBeFalse();
});
