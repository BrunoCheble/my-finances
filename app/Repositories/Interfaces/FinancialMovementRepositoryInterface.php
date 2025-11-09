<?php

namespace App\Repositories\Interfaces;

use App\Models\FinancialMovement;

interface FinancialMovementRepositoryInterface
{
    public function save(array $data, ?int $id = null): FinancialMovement;

    public function delete(FinancialMovement $movement): bool;

    public function find(int $id): ?FinancialMovement;

    public function findByIds(array $ids);

    public function where($column, $operator = null, $value = null, $boolean = 'and');

    public function findByWalletAndInterval(int $walletId, string $startDate, string $endDate);

    public function removeDestinationMovement(int $id);
}
