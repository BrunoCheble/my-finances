<?php

namespace App\Services\Push;

use App\Models\PaymentReminder;
use Carbon\Carbon;

/**
 * Sends the push of the payments due today, from 9h on, once per reminder and day. Safe to call
 * many times (the scheduled trigger runs a few times each morning).
 */
class PaymentReminderNotifier
{
    public const HOUR = 9;

    public static function run(?Carbon $now = null): int
    {
        $now ??= now();
        if ($now->hour < self::HOUR) {
            return 0;
        }

        $today = $now->toDateString();
        // a day the month doesn't have (e.g. 31) comes on its last day
        $days = $now->isLastOfMonth() ? range($now->day, 31) : [$now->day];

        $sent = 0;
        $reminders = PaymentReminder::withoutGlobalScopes()->whereIn('day', $days)
            ->where(fn ($q) => $q->whereNull('last_sent_on')->orWhere('last_sent_on', '<', $today))
            ->get();
        foreach ($reminders as $reminder) {
            $sent += WebPush::sendToUser($reminder->user_id, [
                'title' => __('Payment today: :name', ['name' => $reminder->name]),
                'body' => $reminder->amount ? format_currency($reminder->amount) : __('Due today'),
                'url' => route('payment-reminders.index'),
                'tag' => "payment-{$reminder->id}",
            ]);
            $reminder->update(['last_sent_on' => $today]);
        }
        return $sent;
    }
}
