<?php

namespace App\Repositories;

use App\Models\FinancialMovement;
use App\Repositories\Interfaces\FinancialMovementRepositoryInterface;

class FinancialMovementRepository implements FinancialMovementRepositoryInterface
{
    public function save(array $data, ?int $id = null): FinancialMovement
    {
        if ($id) {
            $movement = FinancialMovement::findOrFail($id);
            $movement->fill($data);
            $movement->save();
            return $movement;
        }

        return FinancialMovement::create($data);
    }


    public function delete(FinancialMovement $movement): bool
    {
        return $movement->delete();
    }

    public function find(int $id): ?FinancialMovement
    {
        return FinancialMovement::find($id);
    }

    public function findByIds(array $ids)
    {
        return FinancialMovement::whereIn('id', $ids)->get();
    }

    public function where($column, $operator = null, $value = null, $boolean = 'and')
    {
        return FinancialMovement::where($column, $operator, $value, $boolean);
    }

    public function findByWalletAndInterval(int $walletId, string $startDate, string $endDate)
    {
        return FinancialMovement::where('wallet_id', $walletId)
            ->whereBetween('date', [$startDate, $endDate])
            ->get();
    }

    public function removeDestinationMovement(int $id)
    {
        return FinancialMovement::where('original_movement_id', $id)->delete();
    }
}
