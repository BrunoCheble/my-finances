<?php

use App\Enums\FinancialMovementType;
use App\Models\FinancialBalance;
use App\Models\FinancialCategory;
use App\Models\FinancialMovement;
use App\Models\StatementAccount;
use App\Models\StatementImport;
use App\Models\User;
use App\Models\Wallet;
use App\Services\CalculateFinancialBalanceService;
use App\Statements\PdfTextExtractor;
use Illuminate\Http\UploadedFile;

function fakeStatementText(string $text): void
{
    app()->instance(PdfTextExtractor::class, new class($text) extends PdfTextExtractor {
        public function __construct(private string $text) {}
        public function extract(string $path): string { return $this->text; }
    });
}

function uploadStatement($test, Wallet $wallet)
{
    return $test->post(route('statement-imports.store'), [
        'file' => UploadedFile::fake()->create('extrato.pdf', 10, 'application/pdf'),
        'wallet_id' => $wallet->id,
    ]);
}

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->bank = Wallet::create(['name' => 'Banco', 'type' => 'debit', 'active' => true]);
    $this->savings = Wallet::create(['name' => 'Poupança', 'type' => 'debit', 'active' => true]);
    $this->groceries = FinancialCategory::create(['name' => 'Supermercado', 'type' => 'expense']);
    $this->salary = FinancialCategory::create(['name' => 'Salário', 'type' => 'income']);

    // history: typed by hand, plus an Uber ride already registered
    FinancialMovement::create(['description' => 'Pingo Doce', 'date' => '2026-07-03', 'wallet_id' => $this->bank->id, 'category_id' => $this->groceries->id, 'amount' => -30, 'type' => 'expense']);
    FinancialMovement::create(['description' => 'Salário Empresa X', 'date' => '2026-07-05', 'wallet_id' => $this->bank->id, 'category_id' => $this->salary->id, 'amount' => 1500, 'type' => 'income']);
    FinancialMovement::create(['description' => 'Uber', 'date' => '2026-08-21', 'wallet_id' => $this->bank->id, 'category_id' => $this->groceries->id, 'amount' => -12.30, 'type' => 'expense']);
    app(CalculateFinancialBalanceService::class)->executeForMovements(FinancialMovement::all());

    fakeStatementText(file_get_contents(__DIR__ . '/../Fixtures/cgd-statement.txt'));
});

it('builds the preview with history suggestions, new lines and duplicates', function () {
    uploadStatement($this, $this->bank)->assertRedirect();

    $import = StatementImport::sole();
    $lines = $import->lines()->get();

    expect($import->bank)->toBe('cgd')
        ->and($lines)->toHaveCount(4)
        // "Pingo Doce" typed by hand is contained in the bank description
        ->and($lines[0]->history_match)->toBe('similar')
        ->and($lines[0]->category_id)->toBe($this->groceries->id)
        ->and($lines[0]->type)->toBe(FinancialMovementType::EXPENSE)
        ->and($lines[1]->history_match)->toBe('exact')
        ->and($lines[1]->type)->toBe(FinancialMovementType::INCOME)
        // never seen before: no suggestion
        ->and($lines[2]->is_new)->toBeTrue()
        ->and($lines[2]->category_id)->toBeNull()
        // same amount one day apart: already registered, unselected
        ->and($lines[3]->duplicate_movement_id)->not->toBeNull()
        ->and($lines[3]->selected)->toBeFalse();

    $this->get(route('statement-imports.show', $import))->assertOk()->assertSee('PINGO DOCE LISBOA');
});

it('imports the selected lines, including a transfer, and updates balances', function () {
    uploadStatement($this, $this->bank);
    $import = StatementImport::sole();
    $lines = $import->lines()->get();

    $choices = [];
    foreach ($lines as $line) {
        $choices[$line->id] = ['selected' => $line->selected ? 1 : null, 'type' => $line->type, 'category_id' => $line->category_id];
    }
    $choices[$lines[2]->id] = ['selected' => 1, 'type' => 'transfer', 'transfer_wallet_id' => $this->savings->id];

    $this->patch(route('statement-imports.update', $import), ['lines' => $choices])->assertRedirect();

    expect($import->fresh()->status)->toBe(StatementImport::STATUS_CONFIRMED)
        ->and(FinancialMovement::where('description', 'TRF PARA POUPANCA')->where('wallet_id', $this->savings->id)->value('amount'))->toEqual(200)
        ->and(FinancialMovement::where('description', 'PINGO DOCE LISBOA 1234567890')->value('amount'))->toEqual(-50.25)
        ->and($lines[3]->fresh()->financial_movement_id)->toBeNull();

    // July closing (1500 - 30) + August: Uber already registered, plus -50.25 + 1500 - 200 imported
    $august = FinancialBalance::where('wallet_id', $this->bank->id)->where('start_date', '2026-08-01')->sole();
    expect((float) $august->calculated_balance)->toBe(round(1500 - 30 - 12.30 - 50.25 + 1500 - 200, 2))
        ->and((float) FinancialBalance::where('wallet_id', $this->savings->id)->sole()->calculated_balance)->toBe(200.0);

    // cannot be imported twice
    $this->patch(route('statement-imports.update', $import), ['lines' => $choices])->assertSessionHasErrors('import');
});

it('blocks the import while a selected new line has no category', function () {
    uploadStatement($this, $this->bank);
    $import = StatementImport::sole();
    $choices = $import->lines()->get()->mapWithKeys(fn ($line) => [$line->id => [
        'selected' => 1, 'type' => $line->type, 'category_id' => $line->category_id,
    ]])->all();

    $before = FinancialMovement::count();
    $this->patch(route('statement-imports.update', $import), ['lines' => $choices])->assertSessionHasErrors();

    expect(FinancialMovement::count())->toBe($before)
        ->and($import->fresh()->status)->toBe(StatementImport::STATUS_PENDING);
});

it('rejects a layout it does not know', function () {
    fakeStatementText("Extrato de outro banco\n2026-08-01 COMPRA -10,00");

    uploadStatement($this, $this->bank)->assertRedirect(route('statement-imports.index'))->assertSessionHas('error');
    expect(StatementImport::count())->toBe(0);
});

it('recognizes bank and account when a file is chosen and remembers the wallet', function () {
    $file = fn () => ['file' => UploadedFile::fake()->create('extrato.pdf', 10, 'application/pdf')];

    $this->postJson(route('statement-imports.identify'), $file())
        ->assertOk()
        ->assertJson(['bank' => 'cgd', 'bank_name' => 'Caixa Geral de Depósitos', 'account_last4' => '0000', 'wallet_id' => null, 'lines' => 4]);

    uploadStatement($this, $this->savings);

    $this->postJson(route('statement-imports.identify'), $file())
        ->assertOk()
        ->assertJson(['wallet_id' => $this->savings->id, 'wallet_name' => 'Poupança']);

    // only a hash and the last digits are stored
    expect(StatementAccount::sole()->account_last4)->toBe('0000')
        ->and(StatementAccount::sole()->account_hash)->not->toContain('0000000000000')
        ->and(StatementImport::sole()->account_last4)->toBe('0000');
});

it('reports an unsupported statement on identify', function () {
    fakeStatementText("Extrato de outro banco\n2026-08-01 COMPRA -10,00");

    $this->postJson(route('statement-imports.identify'), [
        'file' => UploadedFile::fake()->create('extrato.pdf', 10, 'application/pdf'),
    ])->assertStatus(422)->assertJsonStructure(['error']);
});

it('only suggests a transfer from an exactly equal description', function () {
    // internal transfer already registered: bank -> savings
    app(App\Services\SaveFinancialMovementService::class)->execute([
        'description' => 'Transferência para poupança', 'date' => '2026-07-10', 'wallet_id' => $this->bank->id,
        'transfer_to_wallet_id' => $this->savings->id, 'amount' => 100, 'type' => FinancialMovementType::TRANSFER,
    ]);
    fakeStatementText(str_replace(
        ["TRF PARA POUPANCA\t", "UBER   TRIP 9876543210\t"],
        ["TRANSFERENCIA PARA POUPANCA\t", "TRANSFERENCIA PARA POUPANCA JOAO\t"],
        file_get_contents(__DIR__ . '/../Fixtures/cgd-statement.txt')
    ));

    uploadStatement($this, $this->bank);
    $lines = StatementImport::sole()->lines()->get()->keyBy('description');

    $exact = $lines['TRANSFERENCIA PARA POUPANCA'];
    expect($exact->history_match)->toBe('exact')
        ->and($exact->type)->toBe(FinancialMovementType::TRANSFER)
        ->and($exact->transfer_wallet_id)->toBe($this->savings->id);

    // a transfer to someone else is a payment, never an internal transfer by similarity
    $thirdParty = $lines['TRANSFERENCIA PARA POUPANCA JOAO'];
    expect($thirdParty->is_new)->toBeTrue()
        ->and($thirdParty->type)->toBe(FinancialMovementType::EXPENSE)
        ->and($thirdParty->transfer_wallet_id)->toBeNull();
});

it('creates a category from the preview as JSON', function () {
    $this->postJson(route('financial-categories.store'), ['name' => 'Ginásio', 'type' => 'expense', 'active' => 1])
        ->assertCreated()
        ->assertJson(['name' => 'Ginásio', 'type' => 'expense']);

    // same name again is rejected per user
    $this->postJson(route('financial-categories.store'), ['name' => 'Ginásio', 'type' => 'expense'])
        ->assertStatus(422)->assertJsonValidationErrors('name');
});

it('sends category usage counts to the preview', function () {
    uploadStatement($this, $this->bank);

    $this->get(route('statement-imports.show', StatementImport::sole()))
        ->assertOk()
        ->assertSee('"name":"Supermercado","type":"expense","uses":2', false);
});

it('keeps a virtual wallet (money of someone else held in the bank) consistent on import', function () {
    // the bank holds 1000, of which 100 belongs to the daughter: her wallet is -100
    $daughter = Wallet::create(['name' => 'Filha', 'type' => 'debit', 'active' => true]);
    app(App\Services\SaveFinancialMovementService::class)->execute([
        'description' => 'Saldo da filha', 'date' => '2026-07-01', 'wallet_id' => $daughter->id,
        'transfer_to_wallet_id' => $this->bank->id, 'amount' => 100, 'type' => FinancialMovementType::TRANSFER,
    ]);
    app(CalculateFinancialBalanceService::class)->executeForMovements(FinancialMovement::all());

    // bank statement: she receives 1500 (credit) and spends 200 of her money (debit)
    fakeStatementText(str_replace(
        ["SALARIO EMPRESA X\t", "TRF PARA POUPANCA\t"],
        ["PRESENTE PARA A FILHA\t", "COMPRA DA FILHA\t"],
        file_get_contents(__DIR__ . '/../Fixtures/cgd-statement.txt')
    ));
    uploadStatement($this, $this->bank);
    $import = StatementImport::sole();

    $choices = [];
    foreach ($import->lines()->get() as $line) {
        $isDaughters = str_contains($line->description, 'FILHA');
        $choices[$line->id] = $isDaughters
            ? ['selected' => 1, 'type' => 'transfer', 'transfer_wallet_id' => $daughter->id]
            : ['selected' => null];
    }
    $this->patch(route('statement-imports.update', $import), ['lines' => $choices])->assertRedirect();

    $balance = fn (Wallet $wallet) => (float) FinancialBalance::where('wallet_id', $wallet->id)->orderByDesc('start_date')->first()->calculated_balance;

    // her money: -100 - 1500 received + 200 spent = -1400 (more owed to her)
    expect($balance($daughter))->toBe(-1400.0)
        // the bank moved by the real amounts: +1500 - 200
        ->and($balance($this->bank))->toBe(round(1500 - 30 - 12.30 + 100 + 1500 - 200, 2))
        // the user's own money didn't change with her movements
        ->and(round($balance($this->bank) + $balance($daughter), 2))->toBe(round(1500 - 30 - 12.30, 2));
});

it('suggests the reserved amount from the held amount and updates the wallet', function () {
    // accounting 2237.45, available 2207.45: 30 held by the bank
    fakeStatementText(str_replace("Saldo disponível\t2.237,45", "Saldo disponível\t2.207,45",
        file_get_contents(__DIR__ . '/../Fixtures/cgd-statement.txt')));
    uploadStatement($this, $this->bank);
    $import = StatementImport::sole();

    expect($import->suggestedReservedAmount())->toBe(-30.0);
    $this->get(route('statement-imports.show', $import))->assertSee('Valor cativo');

    $this->patch(route('statement-imports.reserved-amount', $import))->assertRedirect()->assertSessionHas('success');
    expect((float) $this->bank->fresh()->reserved_amount)->toBe(-30.0);
});

it('clears the reserved amount when the statement has nothing held', function () {
    $this->bank->update(['reserved_amount' => -30]);
    uploadStatement($this, $this->bank);
    $import = StatementImport::sole();

    expect($import->suggestedReservedAmount())->toEqual(0);
    $this->patch(route('statement-imports.reserved-amount', $import))->assertRedirect();
    expect((float) $this->bank->fresh()->reserved_amount)->toEqual(0);
});

it('does not let an older statement overwrite the reserved amount', function () {
    uploadStatement($this, $this->bank);
    $older = StatementImport::sole();
    $older->update(['period_end' => '2026-07-31', 'available_balance' => $older->closing_balance - 10]);
    uploadStatement($this, $this->bank);

    expect($older->fresh()->isLatestForWallet())->toBeFalse();
    $this->patch(route('statement-imports.reserved-amount', $older))->assertSessionHas('error');
    expect((float) $this->bank->fresh()->reserved_amount)->toEqual(0);
});

it('ignores an available balance above the accounting one (overdraft limit)', function () {
    fakeStatementText(str_replace("Saldo disponível\t2.237,45", "Saldo disponível\t3.237,45",
        file_get_contents(__DIR__ . '/../Fixtures/cgd-statement.txt')));
    uploadStatement($this, $this->bank);

    expect(StatementImport::sole()->suggestedReservedAmount())->toBeNull();
});

it('answers a damaged PDF with a clear message instead of an error page', function () {
    // what the real parser does with a broken file
    app()->instance(PdfTextExtractor::class, new class extends PdfTextExtractor {
        public function __construct() {}
        public function extract(string $path): string { throw new \Exception('Unable to find startxref'); }
    });
    $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('quebrado.pdf', "%PDF-1.4\nnot really a pdf\n%%EOF");

    $this->postJson(route('statement-imports.identify'), ['file' => $file])
        ->assertStatus(422)
        ->assertJsonPath('error', __('This PDF could not be read. Check that it is the original statement from the bank.'));
});

it('does not take transfers to different people for duplicates (the TFI case of production)', function () {
    // same amount, one day apart, another person
    FinancialMovement::create(['description' => 'TFI GABRIELA SANTOS D', 'date' => '2026-08-19', 'wallet_id' => $this->bank->id, 'amount' => -12.30, 'type' => 'transfer']);
    FinancialMovement::where('description', 'Uber')->delete();
    fakeStatementText(str_replace('UBER   TRIP 9876543210', 'TFI CATARINA FILIPA L', file_get_contents(__DIR__ . '/../Fixtures/cgd-statement.txt')));

    uploadStatement($this, $this->bank)->assertRedirect();

    $line = StatementImport::sole()->lines()->where('description', 'TFI CATARINA FILIPA L')->sole();
    expect($line->duplicate_movement_id)->toBeNull()
        ->and($line->selected)->toBeTrue();
});

it('checks the duplicates again when a pending import is opened', function () {
    uploadStatement($this, $this->bank)->assertRedirect();
    $import = StatementImport::sole();
    $uber = $import->lines()->where('description', 'like', 'UBER%')->sole();
    expect($uber->duplicate_movement_id)->not->toBeNull();

    // the movement it matched is deleted: no longer a duplicate, selected again
    FinancialMovement::where('description', 'Uber')->delete();
    $this->get(route('statement-imports.show', $import))->assertOk();

    expect($uber->fresh()->duplicate_movement_id)->toBeNull()
        ->and($uber->fresh()->selected)->toBeTrue();
});

it('lets the user say a line is not a duplicate, and undo it', function () {
    uploadStatement($this, $this->bank)->assertRedirect();
    $import = StatementImport::sole();
    $uber = $import->lines()->where('description', 'like', 'UBER%')->sole();

    $this->patchJson(route('statement-imports.duplicate', [$import, $uber]), ['duplicate' => false])
        ->assertOk()->assertJson(['duplicate' => false, 'dismissed' => true, 'selected' => true]);
    // opening the import again keeps the user's choice
    $this->get(route('statement-imports.show', $import))->assertOk();
    expect($uber->fresh()->duplicate_movement_id)->toBeNull();

    $this->patchJson(route('statement-imports.duplicate', [$import, $uber]), ['duplicate' => true])
        ->assertOk()->assertJson(['duplicate' => true, 'dismissed' => false, 'selected' => false]);
});

it('does not let a line of another import be changed', function () {
    uploadStatement($this, $this->bank)->assertRedirect();
    $import = StatementImport::sole();
    $other = User::factory()->create();
    $this->actingAs($other);

    $this->patchJson(route('statement-imports.duplicate', [$import, $import->lines()->first()]), ['duplicate' => false])->assertNotFound();
});
