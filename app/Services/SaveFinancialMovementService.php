<?php

namespace App\Services;

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

    public function executeForTransfer(mixed $data, ?int $id = null): FinancialMovement
    {
        $data['wallet_id'] = $data['transfer_to_wallet_id'];
        $data['original_movement_id'] = $id;
        $data['amount'] = -1 * $data['amount'];
        return $this->repository->save($data);
    }
}
