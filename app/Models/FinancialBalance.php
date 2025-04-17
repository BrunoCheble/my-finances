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

    public function getRealBalanceFormattedAttribute() {
        return '€ '.number_format($this->real_balance, 2, ',', '.');
    }

    public function getCalculatedBalanceFormattedAttribute() {
        return '€ '.number_format($this->calculated_balance, 2, ',', '.');
    }

    public function getInitialBalanceFormattedAttribute() {
        return '€ '.number_format($this->initial_balance, 2, ',', '.');
    }

    public function getTotalExpenseFormattedAttribute() {
        return '€ '.number_format($this->total_expense, 2, ',', '.');
    }

    public function getTotalIncomeFormattedAttribute() {
        return '€ '.number_format($this->total_income, 2, ',', '.');
    }

    public function getTotalUnidentifiedFormattedAttribute() {
        return '€ '.number_format($this->total_unidentified, 2, ',', '.');
    }

    public function getStartDateFormattedAttribute() {
        return date('d/m/Y', strtotime($this->start_date));
    }

    public function getEndDateFormattedAttribute() {
        return date('d/m/Y', strtotime($this->end_date));
    }

    public function getWalletNameAttribute() {
        return $this->wallet->name;
    }
}
