<?php

namespace App\Models;

use App\Enums\FinancialMovementType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FinancialMovement extends Model
{
    use HasFactory;
    protected $perPage = 20;

    protected $fillable = [
        'description',
        'date',
        'wallet_id',
        'category_id',
        'amount',
        'type',
    ];

    /**
     * Relacionamento: Um movimento financeiro pertence a uma carteira (Wallet)
     */
    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }

    /**
     * Relacionamento: Um movimento financeiro pode ter uma categoria (FinancialCategory)
     */
    public function category()
    {
        return $this->belongsTo(FinancialCategory::class);
    }

    /**
     * Escopo para buscar movimentos de um tipo específico
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Escopo para buscar movimentos dentro de um intervalo de datas
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    public function getTypeNameAttribute() {
        return FinancialMovementType::options()[$this->type];
    }

    public function getIsDebitAttribute() {
        return $this->type === FinancialMovementType::EXPENSE || $this->type === FinancialMovementType::DISCOUNT || ($this->type === FinancialMovementType::TRANSFER && $this->amount < 0);
    }

    public function getAmountFormattedAttribute() {
        $amount = $this->type === FinancialMovementType::TRANSFER && $this->amount < 0 ? $this->amount*-1 : $this->amount;
        return '€ '.number_format($amount, 2, ',', '.');
    }

    public function getDateFormattedAttribute() {
        return date('d/m/Y', strtotime($this->date));
    }
}
