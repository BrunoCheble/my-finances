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
        'original_movement_id',
        'include_alert'
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

    public function originalMovement()
    {
        return $this->belongsTo(FinancialMovement::class, 'original_movement_id');
    }

    public function destinationMovement()
    {
        return $this->hasOne(FinancialMovement::class, 'original_movement_id');
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
}
