<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StatementImportLine extends Model
{
    protected $fillable = [
        'statement_import_id',
        'position',
        'date',
        'value_date',
        'description',
        'normalized_description',
        'amount',
        'bank_balance',
        'balance_mismatch',
        'type',
        'category_id',
        'transfer_wallet_id',
        'is_new',
        'history_match',
        'history_description',
        'history_count',
        'history_last_date',
        'duplicate_movement_id',
        'selected',
        'financial_movement_id',
    ];

    protected $casts = [
        'balance_mismatch' => 'boolean',
        'is_new' => 'boolean',
        'selected' => 'boolean',
    ];

    public function statementImport()
    {
        return $this->belongsTo(StatementImport::class);
    }

    public function category()
    {
        return $this->belongsTo(FinancialCategory::class);
    }

    public function duplicateMovement()
    {
        return $this->belongsTo(FinancialMovement::class, 'duplicate_movement_id');
    }
}
