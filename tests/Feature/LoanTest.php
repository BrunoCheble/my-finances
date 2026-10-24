<?php

use App\Enums\FinancialMovementType;
use App\Models\FinancialBalance;
use App\Models\FinancialMovement;
use App\Models\Loan;
use App\Models\StatementImport;
use App\Models\User;
use App\Models\Wallet;
use App\Services\DashboardKpiService;
use App\Statements\PdfTextExtractor;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->bank = Wallet::create(['name' => 'Banco', 'type' => 'debit', 'active' => true]);
    $this->post(route('financial-movements.store'), [
        'description' => 'Salário', 'date' => '2026-08-01', 'wallet_id' => $this->bank->id,
        'amount' => 2000, 'type' => FinancialMovementType::INCOME,
    ], ['Accept' => 'application/json'])->assertCreated();
});

function netWorth(string $month = '2026-09'): float
{
    return DashboardKpiService::for($month, [])['net_worth'];
}

function bankBalance(Wallet $bank): float
{
    return (float) FinancialBalance::where('wallet_id', $bank->id)->orderByDesc('start_date')->first()->calculated_balance;
}

it('lending keeps the net worth and shows what is still to receive', function () {
    $this->post(route('loans.store'), ['person' => 'João', 'amount' => 1000, 'date' => '2026-09-05', 'wallet_id' => $this->bank->id])->assertRedirect();

    $loan = Loan::sole();
    expect($loan->outstanding)->toBe(1000.0)
        ->and($loan->status)->toBe(Loan::OPEN)
        ->and(bankBalance($this->bank))->toBe(1000.0)
        ->and(netWorth())->toBe(2000.0);
});

it('repayments close the loan and the extra becomes interest income', function () {
    $this->post(route('loans.store'), ['person' => 'João', 'amount' => 1000, 'date' => '2026-09-05', 'wallet_id' => $this->bank->id]);
    $loan = Loan::sole();

    $this->post(route('loans.receive', $loan), ['amount' => 600, 'date' => '2026-09-20', 'wallet_id' => $this->bank->id]);
    expect($loan->fresh()->outstanding)->toBe(400.0)->and($loan->fresh()->status)->toBe(Loan::OPEN);

    $this->post(route('loans.receive', $loan), ['amount' => 500, 'date' => '2026-10-02', 'wallet_id' => $this->bank->id]);
    $interest = FinancialMovement::where('type', FinancialMovementType::INCOME)->where('loan_id', $loan->id)->sole();

    expect($loan->fresh()->status)->toBe(Loan::SETTLED)
        ->and($loan->fresh()->outstanding)->toBe(0.0)
        ->and((float) $interest->amount)->toBe(100.0)
        ->and($interest->category)->not->toBeNull()
        // salary + 100 of interest
        ->and(netWorth('2026-10'))->toBe(2100.0);
});

it('writing off turns what is missing into a loss', function () {
    $this->post(route('loans.store'), ['person' => 'Maria', 'amount' => 300, 'date' => '2026-09-05', 'wallet_id' => $this->bank->id]);
    $loan = Loan::sole();

    $this->patch(route('loans.write-off', $loan))->assertRedirect();

    expect($loan->fresh()->status)->toBe(Loan::WRITTEN_OFF)
        ->and((float) $loan->fresh()->written_off_amount)->toBe(300.0)
        ->and(netWorth(now()->format('Y-m')))->toBe(1700.0);
});

it('lends and receives from the movement form with the person name', function () {
    $this->post(route('financial-movements.store'), [
        'description' => 'Empréstimo', 'date' => '2026-09-05', 'wallet_id' => $this->bank->id, 'amount' => 200,
        'type' => FinancialMovementType::LOAN, 'loan_person' => 'Ana', 'loan_direction' => 'lend',
    ], ['Accept' => 'application/json'])->assertCreated();
    $this->post(route('financial-movements.store'), [
        'description' => 'Ana pagou', 'date' => '2026-09-25', 'wallet_id' => $this->bank->id, 'amount' => 200,
        'type' => FinancialMovementType::LOAN, 'loan_person' => 'ana', 'loan_direction' => 'receive',
    ], ['Accept' => 'application/json'])->assertCreated();

    // same person regardless of case; paid back, so it disappears from the open list
    expect(Loan::count())->toBe(1)->and(Loan::sole()->status)->toBe(Loan::SETTLED);

    // deleting the repayment reopens it
    FinancialMovement::where('description', 'Ana pagou')->sole()->delete();
    expect(Loan::sole()->status)->toBe(Loan::OPEN);
});

it('refuses a repayment from someone without an open loan', function () {
    $this->post(route('financial-movements.store'), [
        'description' => 'Pagamento', 'date' => '2026-09-05', 'wallet_id' => $this->bank->id, 'amount' => 50,
        'type' => FinancialMovementType::LOAN, 'loan_person' => 'Pedro', 'loan_direction' => 'receive',
    ], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonValidationErrors('loan_person');

    expect(Loan::count())->toBe(0);
});

it('imports a bank line as a loan to a person', function () {
    app()->instance(PdfTextExtractor::class, new class extends PdfTextExtractor {
        public function extract(string $path): string { return file_get_contents(__DIR__ . '/../Fixtures/cgd-statement.txt'); }
    });
    $this->post(route('statement-imports.store'), [
        'file' => UploadedFile::fake()->create('extrato.pdf', 10, 'application/pdf'),
        'wallet_id' => $this->bank->id,
    ]);
    $import = StatementImport::sole();
    $lines = $import->lines()->get();

    $choices = $lines->mapWithKeys(fn ($line) => [$line->id => ['selected' => null]])->all();
    $transfer = $lines->firstWhere('description', 'TRF PARA POUPANCA');
    $choices[$transfer->id] = ['selected' => 1, 'type' => 'loan', 'loan_person' => 'Carlos'];

    $this->patch(route('statement-imports.update', $import), ['lines' => $choices])->assertRedirect();

    $loan = Loan::where('person', 'Carlos')->sole();
    expect($loan->outstanding)->toBe(200.0);
});
