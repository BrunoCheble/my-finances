<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FinancialCategory extends Model
{
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
}
