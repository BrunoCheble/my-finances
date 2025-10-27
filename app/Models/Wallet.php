<?php

namespace App\Models;

use App\Traits\HasUserScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Wallet extends Model
{
    use HasUserScope;
    use HasFactory;

    // Campos que podem ser preenchidos em massa
    protected $fillable = [
        'name',
        'color',
        'active',
    ];

    /**
     * Relacionamento: Uma carteira pode ter vários movimentos financeiros
     */
    public function financialMovements()
    {
        return $this->hasMany(FinancialMovement::class);
    }

    /**
     * Relacionamento: Uma carteira pode ter vários saldos financeiros
     */
    public function financialBalances()
    {
        return $this->hasMany(FinancialBalance::class);
    }

    /**
     * Escopo para buscar apenas carteiras ativas
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
