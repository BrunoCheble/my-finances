<?php

namespace App\Models;

use App\Traits\HasUserScope;
use Illuminate\Database\Eloquent\Model;

class StatementImport extends Model
{
    use HasUserScope;

    public const STATUS_PENDING = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';

    protected $fillable = [
        'wallet_id',
        'bank',
        'account_last4',
        'file_name',
        'period_start',
        'period_end',
        'opening_balance',
        'closing_balance',
        'status',
        'confirmed_at',
    ];

    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }

    public function lines()
    {
        return $this->hasMany(StatementImportLine::class)->orderBy('position');
    }

    public function getIsPendingAttribute(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
