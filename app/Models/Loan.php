<?php

namespace App\Models;

use App\Enums\FinancialMovementType;
use App\Traits\HasUserScope;
use Illuminate\Database\Eloquent\Model;

/**
 * Money lent to a person. Lending is a "loan" movement leaving a wallet (negative),
 * a repayment is a "loan" movement coming back (positive). What is still owed is
 * part of the user's net worth.
 */
class Loan extends Model
{
    use HasUserScope;

    public const OPEN = 'open';
    public const SETTLED = 'settled';
    public const WRITTEN_OFF = 'written_off';

    protected $fillable = ['person', 'status', 'written_off_amount', 'closed_on'];

    public function movements()
    {
        return $this->hasMany(FinancialMovement::class)->orderBy('date');
    }

    public function loanMovements()
    {
        return $this->movements()->where('type', FinancialMovementType::LOAN);
    }

    public function scopeOpen($query)
    {
        return $query->where('status', self::OPEN);
    }

    public function getLentAttribute(): float
    {
        return round(-(float) $this->loanMovements->where('amount', '<', 0)->sum('amount'), 2);
    }

    public function getReceivedAttribute(): float
    {
        return round((float) $this->loanMovements->where('amount', '>', 0)->sum('amount'), 2);
    }

    public function getOutstandingAttribute(): float
    {
        return round($this->lent - $this->received - (float) $this->written_off_amount, 2);
    }
}
