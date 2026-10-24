<?php

namespace App\Services;

use App\Enums\FinancialMovementType;
use App\Models\FinancialMovement;
use App\Repositories\Interfaces\FinancialMovementRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class SaveFinancialMovementService
{
    private $repository;

    public function __construct(
        FinancialMovementRepositoryInterface $repository
    )
    {
        $this->repository = $repository;
    }

    public function execute(mixed $data, ?int $id = null): Collection
    {
        $data['include_alert'] = isset($data['include_alert']) ? (bool)$data['include_alert'] : false;

        // loans to a person (older loan movements without a person keep the old behaviour)
        if ($data['type'] === FinancialMovementType::LOAN && !empty($data['loan_person'])) {
            return $this->saveLoan($data, $id);
        }

        switch ($data['type']) {
            case FinancialMovementType::DISCOUNT:
            case FinancialMovementType::EXPENSE:
                $data['amount'] = $data['amount'] * -1;
                break;
            case FinancialMovementType::INCOME:
            case FinancialMovementType::REFUND:
                $data['amount'] = abs($data['amount']);
                break;
            case FinancialMovementType::TRANSFER:
                $data['amount'] = $data['amount'] * -1;
                $data['category_id'] = null;
                break;
            case FinancialMovementType::LOAN:
                $data['category_id'] = null;
                $data['transfer_to_wallet_id'] = null;
                $data['include_alert'] = true;
                break;
            case FinancialMovementType::ADJUSTMENT:
                // signed amount: difference between the real and the calculated balance
                $data['category_id'] = null;
                $data['transfer_to_wallet_id'] = null;
                break;
            default:
                throw new \InvalidArgumentException("Invalid financial movement type: " . $data['type']);
        }

        if ($id) {
            $this->repository->removeDestinationMovement($id);
        }

        $financial = $this->repository->save($data, $id);
        $financials = new Collection([$financial]);

        if (isset($data['transfer_to_wallet_id'])) {
            $transferFinancial = $this->executeForTransfer($data, $financial->id);
            $financials->push($transferFinancial);
        }

        return $financials;
    }

    private function saveLoan(array $data, ?int $id): Collection
    {
        $loans = app(LoanService::class);
        unset($data['transfer_to_wallet_id']);

        if (!$id) {
            $movements = new Collection();
            foreach ($loans->movementsFor($data) as $movementData) {
                $movements->push($this->repository->save($movementData));
            }
            $loans->refresh($movements->first()->loan);
            return $movements;
        }

        // editing: no split, just the sign and the person
        $previousLoan = FinancialMovement::find($id)?->loan;
        $person = trim($data['loan_person']);
        $lending = ($data['loan_direction'] ?? LoanService::LEND) === LoanService::LEND;
        $loan = $lending
            ? $loans->openFor($person)
            : ($loans->findOpen($person) ?? ($previousLoan && mb_strtolower($previousLoan->person) === mb_strtolower($person) ? $previousLoan : $loans->openFor($person)));

        $data['loan_id'] = $loan->id;
        $data['category_id'] = null;
        $data['amount'] = $lending ? -abs((float) $data['amount']) : abs((float) $data['amount']);
        $movement = $this->repository->save($data, $id);

        $loans->refresh($loan);
        if ($previousLoan && $previousLoan->id !== $loan->id) {
            $loans->refresh($previousLoan);
        }
        return new Collection([$movement]);
    }

    public function executeForTransfer(mixed $data, ?int $id = null): FinancialMovement
    {
        $data['wallet_id'] = $data['transfer_to_wallet_id'];
        $data['original_movement_id'] = $id;
        $data['amount'] = -1 * $data['amount'];
        return $this->repository->save($data);
    }
}
