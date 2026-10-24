<?php

namespace App\Services;

use App\Repositories\Interfaces\FinancialBalanceRepositoryInterface;

class SaveFinancialBalanceService
{
    public function __construct(
        private FinancialBalanceRepositoryInterface $repository,
        private CalculateFinancialBalanceService $calculateFinancialBalanceService,
        private AdjustFinancialBalanceService $adjustFinancialBalanceService,
    ) {
    }

    public function execute(mixed $data)
    {
        foreach ($data['wallets'] as $walletId) {
            $previous = $this->repository->findPreviousByWalletAndDate($walletId, $data['start_date']);

            // with history, the initial balance comes from the previous month;
            // without it, the given balance becomes an opening adjustment
            if ($previous) {
                $this->calculateFinancialBalanceService->execute($data['start_date'], $walletId);
                continue;
            }

            $this->adjustFinancialBalanceService->execute(
                $walletId,
                $data['start_date'],
                (float) $data['initial_balance'],
                'Saldo de abertura'
            );
        }
    }
}
