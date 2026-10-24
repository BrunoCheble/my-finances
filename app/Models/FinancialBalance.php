<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasUserScope;

class FinancialBalance extends Model
{
    use HasUserScope;
    use HasFactory;

    protected $fillable = [
        'wallet_id',
        'initial_balance',
        'total_expense',
        'total_income',
        'total_unidentified',
        'calculated_balance',
        'start_date',
        'end_date',
        'reconciled_balance',
        'reconciled_at',
        'reconciled_source',
    ];

    protected $casts = [
        'reconciled_at' => 'datetime',
    ];

    public function getIsReconciledAttribute(): bool
    {
        return $this->reconciled_balance !== null;
    }

    /**
     * Calculated minus confirmed balance; 0 when it still matches (or was never confirmed).
     */
    public function getReconciliationDifferenceAttribute(): float
    {
        // the stored balance: the latest month gets the held amount added in memory for display
        $calculated = $this->exists ? $this->getRawOriginal('calculated_balance') : $this->calculated_balance;
        return $this->is_reconciled ? round((float) $calculated - (float) $this->reconciled_balance, 2) : 0.0;
    }

    public function getIsDivergedAttribute(): bool
    {
        return abs($this->reconciliation_difference) >= 0.005;
    }

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
