<?php

namespace App\Services\Statements;

use App\Enums\FinancialMovementType;
use App\Models\FinancialMovement;
use App\Models\StatementAccount;
use App\Models\StatementImport;
use App\Statements\ParsedStatement;
use App\Statements\PdfTextExtractor;
use App\Statements\StatementParserRegistry;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Parses a statement PDF into a pending import (the preview): each line gets a
 * history-based suggestion, duplicate detection and a running balance check.
 */
class CreateStatementImportService
{
    private const DUPLICATE_DAYS = 3;

    public function __construct(
        private PdfTextExtractor $extractor,
        private StatementParserRegistry $registry,
    ) {
    }

    public function execute(string $path, string $fileName, int $walletId): StatementImport
    {
        $statement = $this->parse($path);

        return DB::transaction(function () use ($statement, $fileName, $walletId) {
            StatementAccount::remember($statement, $walletId);
            return $this->store($statement, $fileName, $walletId);
        });
    }

    /**
     * Bank, masked account and the wallet remembered for it, without creating anything.
     */
    public function identify(string $path): array
    {
        $statement = $this->parse($path);
        $known = StatementAccount::findFor($statement);

        return [
            'bank' => $statement->bank,
            'bank_name' => $this->registry->name($statement->bank),
            'account_last4' => $statement->accountLast4(),
            'wallet_id' => $known?->wallet_id,
            'wallet_name' => $known?->wallet?->name,
            'lines' => count($statement->lines),
            'period_start' => $statement->periodStart,
            'period_end' => $statement->periodEnd,
        ];
    }

    public function parse(string $path): ParsedStatement
    {
        $text = $this->extractor->extract($path);

        if (trim($text) === '') {
            throw new InvalidArgumentException(__('The PDF has no text (scanned document?).'));
        }

        $parser = $this->registry->detect($text);
        if (!$parser) {
            throw new InvalidArgumentException(__('Bank statement layout not supported yet.'));
        }

        $statement = $parser->parse($text);
        if (!$statement->lines) {
            throw new InvalidArgumentException(__('No movements found in the statement.'));
        }

        return $statement;
    }

    private function store(ParsedStatement $statement, string $fileName, int $walletId): StatementImport
    {
        $import = StatementImport::create([
            'wallet_id' => $walletId,
            'bank' => $statement->bank,
            'account_last4' => $statement->accountLast4(),
            'file_name' => $fileName,
            'period_start' => $statement->periodStart,
            'period_end' => $statement->periodEnd,
            'opening_balance' => $statement->openingBalance,
            'closing_balance' => $statement->closingBalance,
            'status' => StatementImport::STATUS_PENDING,
        ]);

        $history = new MovementHistory();
        $candidates = $this->duplicateCandidates($statement, $walletId);
        $runningBalance = $statement->openingBalance;

        foreach ($statement->lines as $position => $line) {
            $suggestion = $history->suggest($line->description);
            $duplicate = $this->takeDuplicate($candidates, $line->date, $line->amount);

            $mismatch = false;
            if ($runningBalance !== null && $line->balance !== null) {
                $mismatch = round($runningBalance + $line->amount, 2) !== round($line->balance, 2);
            }
            $runningBalance = $line->balance ?? ($runningBalance === null ? null : $runningBalance + $line->amount);

            $import->lines()->create([
                'position' => $position,
                'date' => $line->date,
                'value_date' => $line->valueDate,
                'description' => $line->description,
                'normalized_description' => MovementHistory::normalize($line->description),
                'amount' => $line->amount,
                'bank_balance' => $line->balance,
                'balance_mismatch' => $mismatch,
                'type' => self::typeForSign($suggestion?->type, $line->amount),
                'category_id' => $suggestion?->category_id,
                'transfer_wallet_id' => $suggestion?->transfer_wallet_id != $walletId ? $suggestion?->transfer_wallet_id : null,
                'is_new' => $suggestion === null,
                'history_match' => $suggestion?->match,
                'history_description' => $suggestion?->description,
                'history_count' => $suggestion?->count ?? 0,
                'history_last_date' => $suggestion?->last_date,
                'duplicate_movement_id' => $duplicate?->id,
                'selected' => $duplicate === null,
            ]);
        }

        return $import;
    }

    /**
     * Keeps the suggested type coherent with the bank sign
     * (e.g. a positive amount on an "expense" description is a refund).
     */
    public static function typeForSign(?string $type, float $amount): string
    {
        $isCredit = $amount > 0;

        return match ($type) {
            FinancialMovementType::EXPENSE, FinancialMovementType::REFUND =>
                $isCredit ? FinancialMovementType::REFUND : FinancialMovementType::EXPENSE,
            FinancialMovementType::INCOME, FinancialMovementType::DISCOUNT =>
                $isCredit ? FinancialMovementType::INCOME : FinancialMovementType::DISCOUNT,
            FinancialMovementType::TRANSFER, FinancialMovementType::LOAN, FinancialMovementType::ADJUSTMENT => $type,
            default => $isCredit ? FinancialMovementType::INCOME : FinancialMovementType::EXPENSE,
        };
    }

    private function duplicateCandidates(ParsedStatement $statement, int $walletId)
    {
        $dates = array_map(fn ($line) => $line->date, $statement->lines);
        $from = date('Y-m-d', strtotime(min($dates) . ' -' . self::DUPLICATE_DAYS . ' days'));
        $to = date('Y-m-d', strtotime(max($dates) . ' +' . self::DUPLICATE_DAYS . ' days'));

        return FinancialMovement::where('wallet_id', $walletId)
            ->whereBetween('date', [$from, $to])
            ->get()
            ->keyBy('id');
    }

    /**
     * Same wallet, same signed amount, date within a few days; each existing
     * movement can only match one line. Closest date wins.
     */
    private function takeDuplicate($candidates, string $date, float $amount): ?FinancialMovement
    {
        $match = $candidates
            ->filter(fn ($movement) => round((float) $movement->amount, 2) === round($amount, 2)
                && abs(strtotime($movement->date) - strtotime($date)) <= self::DUPLICATE_DAYS * 86400)
            ->sortBy(fn ($movement) => abs(strtotime($movement->date) - strtotime($date)))
            ->first();

        if ($match) {
            $candidates->forget($match->id);
        }

        return $match;
    }
}
