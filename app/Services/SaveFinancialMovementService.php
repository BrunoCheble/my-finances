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
                $data['category_id'] = null;
                break;
            case FinancialMovementType::LOAN:
                $data['category_id'] = null;
                $data['transfer_to_wallet_id'] = null;
                $data['include_alert'] = true;
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

    public function executeForTransfer(mixed $data, ?int $id = null): FinancialMovement
    {
        $data['wallet_id'] = $data['transfer_to_wallet_id'];
        $data['original_movement_id'] = $id;
        $data['amount'] = -1 * $data['amount'];
        return $this->repository->save($data);
    }
}
