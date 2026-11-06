<?php

namespace App\Services\Statements;

use App\Statements\JsonStatementParser;

use App\Enums\FinancialMovementType;
use App\Models\StatementImport;
use App\Models\StatementImportLine;
use App\Services\Cache\DashboardCacheService;
use App\Services\CalculateFinancialBalanceService;
use App\Services\ReconciliationService;
use App\Services\SaveFinancialMovementService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Turns the selected preview lines into financial movements.
 */
class ConfirmStatementImportService
{
    public const CATEGORIZED_TYPES = [
        FinancialMovementType::EXPENSE,
        FinancialMovementType::INCOME,
        FinancialMovementType::REFUND,
        FinancialMovementType::DISCOUNT,
    ];

    public function __construct(
        private SaveFinancialMovementService $saveFinancialMovementService,
        private CalculateFinancialBalanceService $calculateFinancialBalanceService,
    ) {
    }

    /**
     * @param array $choices line id => [selected, type, category_id, transfer_wallet_id]
     */
    public function execute(StatementImport $import, array $choices): int
    {
        if (!$import->is_pending) {
            throw ValidationException::withMessages(['import' => __('This import was already confirmed.')]);
        }

        $lines = $import->lines()->get();
        $this->applyChoices($lines, $choices, self::amountsEditable($import));
        $this->validate($import, $lines);

        return DB::transaction(function () use ($import, $lines) {
            $affected = collect();
            $created = 0;

            foreach ($lines->where('selected', true) as $line) {
                $movements = $this->saveFinancialMovementService->execute($this->movementData($import, $line));
                $line->financial_movement_id = $movements->firstWhere('wallet_id', $import->wallet_id)?->id
                    ?? $movements->first()->id;
                $line->save();

                $affected = $affected->merge($movements->map(fn ($m) => $m->only('wallet_id', 'date')));
                $created++;
            }

            $this->calculateFinancialBalanceService->executeForMovements($affected);
            $import->update(['status' => StatementImport::STATUS_CONFIRMED, 'confirmed_at' => now()]);

            // the bank says the same as the app: the month is confirmed
            app(ReconciliationService::class)->reconcileFromStatement($import);
            DashboardCacheService::clearAllByUser();

            return $created;
        });
    }

    /**
     * The review as it is filled in, kept on the pending import (nothing is imported): a partial
     * review is found again after a reload.
     */
    public function saveDraft(StatementImport $import, array $choices): void
    {
        if (!$import->is_pending) {
            throw ValidationException::withMessages(['import' => __('This import was already confirmed.')]);
        }
        $this->applyChoices($import->lines()->get(), $choices, self::amountsEditable($import));
    }

    /**
     * A pasted JSON is the user's own data: its amounts can be corrected on the review. A PDF's
     * amounts are the bank's; only a line that came without one is typed.
     */
    public static function amountsEditable(StatementImport $import): bool
    {
        return $import->bank === JsonStatementParser::BANK;
    }

    private function applyChoices($lines, array $choices, bool $amountsEditable = false): void
    {
        foreach ($lines as $line) {
            $choice = $choices[$line->id] ?? [];
            $line->selected = (bool) ($choice['selected'] ?? false);
            $line->type = $choice['type'] ?? $line->type;
            $line->category_id = ($choice['category_id'] ?? null) ?: null;
            $line->transfer_wallet_id = ($choice['transfer_wallet_id'] ?? null) ?: null;
            $line->loan_person = trim((string) ($choice['loan_person'] ?? '')) ?: null;
            // edited on the review; left empty, the bank's text stays
            $line->description = mb_substr(trim((string) ($choice['description'] ?? '')), 0, 255) ?: $line->description;
            // a line that came without an amount (0), or of a JSON: typed on the review, the sign follows the type
            if (($amountsEditable || (float) $line->amount == 0) && array_key_exists('amount', $choice)) {
                $typed = JsonStatementParser::parseAmount((string) $choice['amount']) ?? 0.0;
                $line->amount = match ($line->type) {
                    FinancialMovementType::EXPENSE, FinancialMovementType::DISCOUNT => -abs($typed),
                    FinancialMovementType::INCOME, FinancialMovementType::REFUND => abs($typed),
                    default => $typed,
                };
            }
            $line->save();
        }
    }

    private function validate(StatementImport $import, $lines): void
    {
        $errors = [];
        foreach ($lines->where('selected', true) as $line) {
            $label = '#' . ($line->position + 1) . ' ' . $line->description;

            if (trim($line->description) === '') {
                $errors["lines.{$line->id}"] = __('Type the description') . ': #' . ($line->position + 1);
            } elseif ((float) $line->amount == 0) {
                $errors["lines.{$line->id}"] = __('Type the amount') . ": $label";
            } elseif (!array_key_exists($line->type, FinancialMovementType::options())) {
                $errors["lines.{$line->id}"] = __('Invalid type') . ": $label";
            } elseif (in_array($line->type, self::CATEGORIZED_TYPES) && !$line->category_id) {
                $errors["lines.{$line->id}"] = __('Choose a category') . ": $label";
            } elseif ($line->type === FinancialMovementType::LOAN && !$line->loan_person) {
                $errors["lines.{$line->id}"] = __('Choose the person of the loan') . ": $label";
            } elseif ($line->type === FinancialMovementType::TRANSFER
                && (!$line->transfer_wallet_id || $line->transfer_wallet_id == $import->wallet_id)) {
                $errors["lines.{$line->id}"] = __('Choose the other wallet of the transfer') . ": $label";
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Maps a bank line (signed amount) to the input SaveFinancialMovementService expects.
     */
    private function movementData(StatementImport $import, StatementImportLine $line): array
    {
        $amount = (float) $line->amount;
        $data = [
            'description' => $line->description,
            'date' => $line->date,
            'wallet_id' => $import->wallet_id,
            'category_id' => $line->category_id,
            'type' => $line->type,
            'amount' => abs($amount),
        ];

        return match ($line->type) {
            // money leaving this wallet goes to the other one, and vice versa
            FinancialMovementType::TRANSFER => array_merge($data, $amount < 0
                ? ['transfer_to_wallet_id' => $line->transfer_wallet_id]
                : ['wallet_id' => $line->transfer_wallet_id, 'transfer_to_wallet_id' => $import->wallet_id]),
            // money leaving the wallet is a loan, money coming back is a repayment
            FinancialMovementType::LOAN => array_merge($data, [
                'amount' => abs($amount),
                'loan_person' => $line->loan_person,
                'loan_direction' => $amount < 0 ? 'lend' : 'receive',
            ]),
            FinancialMovementType::ADJUSTMENT => array_merge($data, ['amount' => $amount]),
            default => $data,
        };
    }
}
