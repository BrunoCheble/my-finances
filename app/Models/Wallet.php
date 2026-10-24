<?php

namespace App\Models;

use App\Traits\HasUserScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Wallet extends Model
{
    use HasUserScope;
    use HasFactory;

    public const TYPE_DEBIT = 'debit';
    public const TYPE_CREDIT = 'credit';
    // money of someone else kept in the user's banks; its balance is negative
    // (what the user owes), so the sum of all wallets is the user's own money
    public const TYPE_THIRD_PARTY = 'third_party';

    public static function types(): array
    {
        return [
            self::TYPE_DEBIT => __('Debit'),
            self::TYPE_CREDIT => __('Credit'),
            self::TYPE_THIRD_PARTY => __('Third-party money'),
        ];
    }

    public function getIsThirdPartyAttribute(): bool
    {
        return $this->type === self::TYPE_THIRD_PARTY;
    }

    /**
     * Wallets that are real bank accounts/cards (not someone else's money).
     */
    public function scopeOwn($query)
    {
        return $query->where('type', '!=', self::TYPE_THIRD_PARTY);
    }

    // Campos que podem ser preenchidos em massa
    protected $fillable = [
        'name',
        'color',
        'active',
        'type',
        'reserved_amount',
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
