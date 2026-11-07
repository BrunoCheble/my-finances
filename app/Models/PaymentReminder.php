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

    protected $fillable = ['name', 'day', 'amount', 'reference', 'notify_before', 'paid_month', 'last_sent_on'];

    protected $casts = ['amount' => 'decimal:2', 'notify_before' => 'boolean', 'last_sent_on' => 'date'];

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

    /**
     * The new-reminder form filled from a movement that repeats every month: its day, amount and
     * description (without the changing numbers) as the reference.
     */
    public static function urlFromMovement(FinancialMovement $movement): string
    {
        $reference = \App\Services\Statements\MovementHistory::normalize($movement->description);
        return route('payment-reminders.index', array_filter([
            'name' => trim(preg_replace('/\s+/', ' ', preg_replace('/\d{4,}/', ' ', $movement->description))),
            'day' => (int) substr($movement->date, 8, 2),
            'amount' => number_format(abs((float) $movement->amount), 2, '.', ''),
            'reference' => $reference,
        ])) . '#new-title';
    }

    /** Upper case, no accents, only letters and digits: "Débito Renda-Nov" -> "DEBITO RENDA NOV". */
    public static function normalize(?string $text): string
    {
        $text = strtoupper(Str::ascii((string) $text));
        return trim(preg_replace('/[^A-Z0-9]+/', ' ', $text));
    }
}
