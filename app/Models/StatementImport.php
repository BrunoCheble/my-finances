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
        'available_balance',
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

    /**
     * Reserved amount the wallet should have according to this statement:
     * minus the amount held by the bank (accounting - available balance).
     * Null when unknown, or when available is higher (e.g. an overdraft limit).
     */
    public function suggestedReservedAmount(): ?float
    {
        if ($this->closing_balance === null || $this->available_balance === null) {
            return null;
        }
        $held = round($this->closing_balance - $this->available_balance, 2);

        return $held >= 0 ? -$held : null;
    }

    /**
     * The held amount is a snapshot of the statement date: only the most
     * recent statement of the wallet may update it.
     */
    public function isLatestForWallet(): bool
    {
        return !self::where('wallet_id', $this->wallet_id)
            ->where('id', '!=', $this->id)
            ->where(fn ($query) => $query->where('period_end', '>', $this->period_end)
                ->orWhere(fn ($same) => $same->where('period_end', $this->period_end)->where('id', '>', $this->id)))
            ->exists();
    }

    public function getIsPendingAttribute(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
