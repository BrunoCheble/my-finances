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
    public function execute($ids)
    {
        $movements = $this->financialMovementRepository->findByIds($ids);
        foreach ($movements as $movement) {
            $movement->delete();
        }
        return $movements;
    }
}
