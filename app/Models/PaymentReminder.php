<?php

namespace App\Models;

use App\Traits\HasUserScope;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A payment due every month on the same day; a push notification is sent that morning. It counts as
 * paid in a month when a movement of that month has the reference in its description, or when it was
 * marked as paid by hand.
 */
class PaymentReminder extends Model
{
    use HasUserScope;

    protected $fillable = ['name', 'day', 'amount', 'reference', 'paid_month', 'last_sent_on'];

    protected $casts = ['amount' => 'decimal:2', 'last_sent_on' => 'date'];

    /** The day it is due in that month (a day the month doesn't have is its last day). */
    public function dueDate(string $month): Carbon
    {
        $start = Carbon::createFromFormat('Y-m-d', $month . '-01')->startOfDay();
        return $start->day(min($this->day, $start->daysInMonth));
    }

    /**
     * How it was paid in the month (Y-m): ['manual' => bool, 'movement' => ?FinancialMovement], or null.
     */
    public function paymentIn(string $month): ?array
    {
        $reference = self::normalize($this->reference);
        if ($reference !== '') {
            $start = Carbon::createFromFormat('Y-m-d', $month . '-01');
            $movement = FinancialMovement::withoutGlobalScopes()
                ->where('user_id', $this->user_id)
                ->whereBetween('date', [$start->toDateString(), $start->copy()->endOfMonth()->toDateString()])
                ->orderBy('date')
                ->get()
                // whole words: "MB" doesn't match "MBWAY"
                ->first(fn ($movement) => str_contains(' ' . self::normalize($movement->description) . ' ', " $reference "));
            if ($movement) {
                return ['manual' => false, 'movement' => $movement];
            }
        }
        return $this->paid_month === $month ? ['manual' => true, 'movement' => null] : null;
    }

    /** Upper case, no accents, only letters and digits: "Débito Renda-Nov" -> "DEBITO RENDA NOV". */
    public static function normalize(?string $text): string
    {
        $text = strtoupper(Str::ascii((string) $text));
        return trim(preg_replace('/[^A-Z0-9]+/', ' ', $text));
    }
}
