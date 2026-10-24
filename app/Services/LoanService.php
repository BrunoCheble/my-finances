<?php

namespace App\Services;

use App\Enums\FinancialMovementType;
use App\Models\FinancialCategory;
use App\Models\FinancialMovement;
use App\Models\Loan;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Loans to people: lending moves money out of a wallet into "to receive" (net worth
 * unchanged); a repayment above what is owed is interest (income); writing off the
 * rest is a loss. A loan closes by itself when nothing is owed.
 */
class LoanService
{
    public const LEND = 'lend';
    public const RECEIVE = 'receive';

    /**
     * The open loan of a person (case-insensitive), or a new one when lending.
     */
    public function findOpen(string $person): ?Loan
    {
        return Loan::open()->whereRaw('LOWER(person) = ?', [mb_strtolower(trim($person))])->first();
    }

    public function openFor(string $person): Loan
    {
        return $this->findOpen($person) ?? Loan::create(['person' => trim($person), 'status' => Loan::OPEN]);
    }

    /**
     * Movement data for a new loan movement; a repayment above what is owed is
     * split into the repayment and an "interest" income movement.
     *
     * @return array[] one or two movement data arrays
     */
    public function movementsFor(array $data): array
    {
        $person = trim((string) ($data['loan_person'] ?? ''));
        $amount = abs((float) $data['amount']);
        $base = array_merge($data, ['type' => FinancialMovementType::LOAN, 'category_id' => null, 'transfer_to_wallet_id' => null]);

        if (($data['loan_direction'] ?? self::LEND) === self::LEND) {
            $loan = $this->openFor($person);
            return [array_merge($base, ['loan_id' => $loan->id, 'amount' => -$amount])];
        }

        $loan = $this->findOpen($person);
        if (!$loan) {
            throw ValidationException::withMessages(['loan_person' => __('There is no open loan for :person.', ['person' => $person])]);
        }

        $owed = $loan->outstanding;
        if ($amount <= $owed + 0.004) {
            return [array_merge($base, ['loan_id' => $loan->id, 'amount' => $amount])];
        }

        return [
            array_merge($base, ['loan_id' => $loan->id, 'amount' => $owed]),
            array_merge($data, [
                'type' => FinancialMovementType::INCOME,
                'amount' => round($amount - $owed, 2),
                'category_id' => $this->interestCategory()->id,
                'description' => __('Loan interest: :person', ['person' => $loan->person]),
                'loan_id' => $loan->id,
                'transfer_to_wallet_id' => null,
            ]),
        ];
    }

    /**
     * Closes the loan when nothing is owed anymore, reopens it otherwise.
     */
    public function refresh(?Loan $loan, ?string $date = null): void
    {
        if (!$loan) {
            return;
        }
        $loan->load('loanMovements');

        if ($loan->status === Loan::WRITTEN_OFF) {
            return;
        }
        $settled = abs($loan->outstanding) < 0.005 && $loan->lent > 0;
        $loan->update([
            'status' => $settled ? Loan::SETTLED : Loan::OPEN,
            'closed_on' => $settled ? ($date ?? $loan->loanMovements->max('date')) : null,
        ]);
    }

    /**
     * Gives up what is still owed: it becomes a loss and the loan closes.
     */
    public function writeOff(Loan $loan, string $date): float
    {
        $loss = $loan->outstanding;
        $loan->update([
            'status' => Loan::WRITTEN_OFF,
            'written_off_amount' => $loss,
            'closed_on' => $date,
        ]);
        return $loss;
    }

    /**
     * Total still to receive at the end of each given date ("Y-m-d"), keyed by date.
     */
    public static function outstandingAt(array $dates): array
    {
        $movements = FinancialMovement::whereNotNull('loan_id')->where('type', FinancialMovementType::LOAN)->get(['loan_id', 'date', 'amount']);
        $writeOffs = Loan::where('status', Loan::WRITTEN_OFF)->get(['id', 'written_off_amount', 'closed_on']);

        $result = [];
        foreach ($dates as $date) {
            $owed = -(float) $movements->where('date', '<=', $date)->sum('amount');
            $owed -= (float) $writeOffs->where('closed_on', '<=', $date)->sum('written_off_amount');
            $result[$date] = round($owed, 2);
        }
        return $result;
    }

    private function interestCategory(): FinancialCategory
    {
        return FinancialCategory::firstOrCreate(
            ['name' => __('Loan interest')],
            ['type' => 'income', 'active' => true]
        );
    }

    /**
     * People with an open loan, for pickers.
     */
    public static function openPeople(): Collection
    {
        return Loan::open()->with('loanMovements')->orderBy('person')->get()
            ->map(fn (Loan $loan) => ['person' => $loan->person, 'outstanding' => $loan->outstanding])
            ->values();
    }
}
