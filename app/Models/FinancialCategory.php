<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasUserScope;

class FinancialCategory extends Model
{
    use HasUserScope;
    use HasFactory;

    protected $primaryKey = 'id'; // Altere se a chave for diferente de 'id'

    protected $perPage = 20;
    // Campos que podem ser preenchidos em massa
    protected $fillable = [
        'name',
        'expected_total',
        'active',
        'type'
    ];

    /**
     * Relacionamento: Uma categoria financeira pode ter vários movimentos financeiros
     */
    public function financials()
    {
        return $this->hasMany(FinancialMovement::class, 'category_id');
    }

    /**
     * Escopo para buscar categorias ativas
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Monthly forecast in the category's own direction: positive is the usual (spend for an expense,
     * receive for an income), negative the opposite (e.g. -130 on an income = a loss). Stored with the
     * sign of the money (expenses negative). Null without a forecast.
     */
    public function forecast(): ?float
    {
        $value = (float) $this->expected_total;
        return $value != 0 ? ($this->type === 'expense' ? -$value : $value) : null;
    }

    /** The stored expected_total for a forecast typed in the category's direction. */
    public static function storedForecast(string $type, float $forecast): float
    {
        return $type === 'expense' ? -$forecast : $forecast;
    }
}
