<?php

namespace App\Services\Push;

use App\Models\PaymentReminder;
use Carbon\Carbon;

/**
 * From 9h on, sends the push of the payments not paid yet: due today, due tomorrow (when the reminder
 * asks for it) and still unpaid 2 days after their day. At most one push per reminder and day, so it is
 * safe to call many times (the scheduled trigger runs a few times each morning).
 */
class PaymentReminderNotifier
{
    public const HOUR = 9;
    public const LATE_DAYS = 2;

    public static function run(?Carbon $now = null): int
    {
        $now ??= now();
        if ($now->hour < self::HOUR) {
            return 0;
        }
        $today = $now->copy()->startOfDay();

        $sent = 0;
        $reminders = PaymentReminder::withoutGlobalScopes()
            ->where(fn ($q) => $q->whereNull('last_sent_on')->orWhere('last_sent_on', '<', $today->toDateString()))
            ->get();
        foreach ($reminders as $reminder) {
            $push = self::push($reminder, $today);
            if (!$push) {
                continue;
            }
            $sent += WebPush::sendToUser($reminder->user_id, $push + [
                'url' => route('payment-reminders.index'),
                'tag' => "payment-{$reminder->id}",
            ]);
            $reminder->update(['last_sent_on' => $today->toDateString()]);
        }
        return $sent;
    }

    /** The push of the reminder for today (title, body), or null. */
    private static function push(PaymentReminder $reminder, Carbon $today): ?array
    {
        $amount = $reminder->amount ? format_currency($reminder->amount) : null;

        $checks = [
            [$today, __('Payment today: :name', ['name' => $reminder->name]), $amount ?? __('Due today')],
            [$today->copy()->addDay(), __('Payment tomorrow: :name', ['name' => $reminder->name]), $amount ?? __('Due tomorrow')],
            [$today->copy()->subDays(self::LATE_DAYS), __(':name not paid yet', ['name' => $reminder->name]),
                ($amount ? "$amount · " : '') . __('It was due on :date.', ['date' => date_format_custom($today->copy()->subDays(self::LATE_DAYS)->toDateString())])],
        ];
        foreach ($checks as $i => [$day, $title, $body]) {
            $month = $day->format('Y-m');
            if (!$reminder->dueDate($month)->isSameDay($day)
                || ($i === 1 && !$reminder->notify_before)
                // a reminder created after its day only counts from the next month
                || ($i === 2 && $reminder->created_at && $reminder->created_at->gt($day->copy()->endOfDay()))
                || $reminder->paymentIn($month)) {
                continue;
            }
            return ['title' => $title, 'body' => $body];
        }
        return null;
    }
}
