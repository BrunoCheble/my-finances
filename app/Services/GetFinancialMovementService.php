<?php

namespace App\Services;
use App\Models\FinancialMovement;

class GetFinancialMovementService
{
    static function execute($attribute, $search, $sort, $order, $date_from, $date_to, $wallet, $paginate = true) {

        $transacations = FinancialMovement::orderBy($sort ?? 'date', $order ?? 'desc')->orderBy('created_at', 'desc');

        if ($attribute && $search) {
            $transacations = $transacations->where($attribute, 'like', '%' . $search . '%');
        }

        if ($wallet) {
            $transacations = $transacations->where('wallet_id', $wallet);
        }

        if (!$date_from || !$date_to) {
            return $paginate ? $transacations->paginate() : $transacations->get();
        }

        $transacations->whereBetween('date', [$date_from, $date_to]);

        return $paginate ? $transacations->paginate() : $transacations->get();
    }
}
