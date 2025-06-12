<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FinancialBalance extends Model
{
    use HasFactory;

    protected $fillable = [
        'wallet_id',
        'initial_balance',
        'total_expense',
        'total_income',
        'total_unidentified',
        'calculated_balance',
        'real_balance',
        'start_date',
        'end_date',
    ];

    /**
     * Relacionamento: Um saldo financeiro pertence a uma carteira (Wallet)
     */
    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }

    public function getWalletNameAttribute() {
        return $this->wallet->name;
    }
}
