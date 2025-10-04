<?php

namespace App\Services;
use App\Models\FinancialMovement;

class GetFinancialMovementService
{
    static function execute($attribute, $search, $sort, $order, $date_from, $date_to, $wallet, $paginate = true, $limit = null) {

        $transacations = FinancialMovement::orderBy($sort ?? 'date', $order ?? 'desc')->orderBy('created_at', 'desc');

        $foreignKeys = ['category_id', 'wallet_id'];

        if (in_array($attribute, $foreignKeys) && $search) {
            $transacations = $transacations->where($attribute, $search);
        }
        else if ($attribute === 'type') {
            switch ($search) {
                case 'expense':
                    $transacations = $transacations->whereIn('type', ['expense', 'refund']);
                    break;
                case 'income':
                    $transacations = $transacations->whereIn('type', ['income', 'discount']);
                    break;
                case 'transfer':
                    $transacations = $transacations->where('type', 'transfer');
                    break;
            }
        }
        else if ($attribute && $search) {
            $transacations = $transacations->where($attribute, 'like', '%' . $search . '%');
        }

        if ($wallet) {
            $transacations = $transacations->where('wallet_id', $wallet);
        }

        if ($limit) {
            $transacations->limit($limit);
        }

        if (!$date_from || !$date_to) {
            return $paginate ? $transacations->paginate() : $transacations->get();
        }

        $transacations->whereBetween('date', [$date_from, $date_to]);

        return $paginate ? $transacations->paginate() : $transacations->get();
    }
}
