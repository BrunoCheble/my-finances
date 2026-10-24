<?php

namespace App\Services;

use App\Repositories\Interfaces\FinancialMovementRepositoryInterface;

class BulkDeleteFinancialMovementService
{
    private FinancialMovementRepositoryInterface $financialMovementRepository;
    public function __construct(
        FinancialMovementRepositoryInterface $financialMovementRepository,
    ) {
        $this->financialMovementRepository = $financialMovementRepository;
    }

    /**
     * Returns the deleted movements, including cascaded transfer mirrors,
     * so the balances of every affected wallet get recalculated.
     */
    public function execute($ids)
    {
        $movements = $this->financialMovementRepository->findByIds($ids);
        $deleted = collect();
        foreach ($movements as $movement) {
            $deleted->push($movement);
            if ($movement->destinationMovement) {
                $deleted->push($movement->destinationMovement);
            }
            $movement->delete();
        }
        return $deleted;
    }
}
