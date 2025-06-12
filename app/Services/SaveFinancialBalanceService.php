<?php

namespace App\Services;

use App\Models\FinancialBalance;
use App\Repositories\Interfaces\FinancialBalanceRepositoryInterface;

class SaveFinancialBalanceService
{
    private FinancialBalanceRepositoryInterface $repository;

    public function __construct(
        FinancialBalanceRepositoryInterface $financialBalanceRepository
    ) {
        $this->repository = $financialBalanceRepository;
    }
    public function execute(mixed $data)
    {
        foreach ($data['wallets'] as $wallet) {
            $data['wallet_id'] = $wallet;
            $balance = $this->repository->findByWalletAndDates($data['wallet_id'], $data['start_date'], $data['end_date']);

            $old_date = date('Y-m-d', strtotime($data['start_date']) - 1);
            $old_balance = $this->repository->findByWalletByEndDate($data['wallet_id'], $old_date);

            if ($old_balance) {
                $data['initial_balance'] = $old_balance->calculated_balance;
            }

            if (!$balance) $this->repository->create($data);
        }
    }
}
