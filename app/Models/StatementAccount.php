<?php

namespace App\Models;

use App\Statements\ParsedStatement;
use App\Traits\HasUserScope;
use Illuminate\Database\Eloquent\Model;

class StatementAccount extends Model
{
    use HasUserScope;

    protected $fillable = [
        'bank',
        'account_hash',
        'account_last4',
        'wallet_id',
    ];

    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }

    public static function hash(string $bank, string $account): string
    {
        return hash_hmac('sha256', $bank . '|' . $account, config('app.key'));
    }

    public static function findFor(ParsedStatement $statement): ?self
    {
        if (!$statement->account) {
            return null;
        }

        return self::where('bank', $statement->bank)
            ->where('account_hash', self::hash($statement->bank, $statement->account))
            ->first();
    }

    public static function remember(ParsedStatement $statement, int $walletId): void
    {
        if (!$statement->account) {
            return;
        }

        self::updateOrCreate(
            ['bank' => $statement->bank, 'account_hash' => self::hash($statement->bank, $statement->account)],
            ['account_last4' => $statement->accountLast4(), 'wallet_id' => $walletId]
        );
    }
}
