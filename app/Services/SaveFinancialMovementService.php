<?php

namespace App\Services;

use App\Models\FinancialMovement;
use App\Repositories\Interfaces\FinancialBalanceRepositoryInterface;
use App\Repositories\Interfaces\FinancialMovementRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class SaveFinancialMovementService
{
    private $repository;
    private $financialBalanceService;

    public function __construct(
        FinancialMovementRepositoryInterface $repository,
        CalculateFinancialBalanceService $financialBalanceService
    )
    {
        $this->repository = $repository;
        $this->financialBalanceService = $financialBalanceService;
    }

    public function execute(mixed $data, ?int $id = null): Collection
    {
        if ($id) {
            $this->repository->removeDestinationMovement($id);
        }

        $financial = $this->repository->save($data, $id);
        $this->financialBalanceService->execute($financial->date, $financial->wallet_id);

        $financials = new Collection([$financial]);

        if (isset($data['transfer_to_wallet_id'])) {
            $transferFinancial = $this->executeForTransfer($data, $financial->id);
            $financials->push($transferFinancial);
        }

        return $financials;
    }

    public function executeForTransfer(mixed $data, ?int $id = null): FinancialMovement
    {
        $data['wallet_id'] = $data['transfer_to_wallet_id'];
        $data['original_movement_id'] = $id;
        $data['amount'] = -1 * $data['amount'];

        $financial = $this->repository->save($data);
        $this->financialBalanceService->execute($financial->date, $financial->wallet_id);
        return $financial;
    }
}
