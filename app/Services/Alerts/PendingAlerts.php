<?php

namespace App\Services\Alerts;

use App\Models\FinancialBalance;
use App\Models\Loan;
use App\Models\Wallet;
use Carbon\Carbon;

/**
 * Things waiting for the user: uncategorized movements, overdrawn wallets,
 * loans open for a long time and manual reminders ("include alert").
 */
class PendingAlerts
{
    public const OLD_LOAN_DAYS = 60;

    public static function check(AlertContext $context): array
    {
        return array_merge(
            self::uncategorized($context),
            self::overdrawn($context),
            self::oldLoans($context),
            self::reminders($context),
        );
    }

    private static function uncategorized(AlertContext $context): array
    {
        $missing = $context->movements->whereIn('type', ['expense', 'income', 'refund', 'discount'])->whereNull('category_id');
        if ($missing->isEmpty()) {
            return [];
        }
        return [AlertContext::alert(
            'uncategorized',
            'info',
            'tags',
            trans_choice(':count movement without category|:count movements without category', $missing->count(), ['count' => $missing->count()]),
            __('They are left out of the category reports and forecasts (:amount).', ['amount' => format_currency(abs($missing->sum('amount')))]),
            __('See movements'),
            $context->movementsLink(),
            $missing->count(),
        )];
    }

    private static function overdrawn(AlertContext $context): array
    {
        // credit cards are negative by nature; third-party money is negative on purpose
        $wallets = Wallet::where('type', Wallet::TYPE_DEBIT)->where('active', true)->get();
        $alerts = [];
        foreach ($wallets as $wallet) {
            $balance = FinancialBalance::where('wallet_id', $wallet->id)
                ->where('start_date', '<=', $context->end->toDateString())
                ->orderByDesc('start_date')->first();
            if (!$balance || $balance->calculated_balance >= 0) {
                continue;
            }
            $alerts[] = AlertContext::alert(
                "overdrawn-{$wallet->id}",
                'critical',
                'triangle-exclamation',
                __(':wallet is negative', ['wallet' => $wallet->name]),
                __('Balance of :amount at the end of the month.', ['amount' => format_currency($balance->calculated_balance)]),
                __('See wallet'),
                route('financial-movements.index', ['wallet_id' => $wallet->id]),
                abs($balance->calculated_balance),
            );
        }
        return $alerts;
    }

    private static function oldLoans(AlertContext $context): array
    {
        $alerts = [];
        foreach (Loan::open()->with('loanMovements')->get() as $loan) {
            $since = $loan->loanMovements->first()?->date;
            if (!$since || $loan->outstanding <= 0) {
                continue;
            }
            $days = Carbon::parse($since)->diffInDays(min(now(), $context->end));
            if ($days < self::OLD_LOAN_DAYS) {
                continue;
            }
            $months = max(1, (int) floor($days / 30));
            $alerts[] = AlertContext::alert(
                "loan-{$loan->id}",
                'info',
                'hand-holding-dollar',
                __(':person owes you :amount', ['person' => $loan->person, 'amount' => format_currency($loan->outstanding)]),
                __('Open since :date (:months).', [
                    'date' => date_format_custom($since),
                    'months' => trans_choice(':count month|:count months', $months, ['count' => $months]),
                ]),
                __('See loans'),
                route('loans.index'),
                $days,
            );
        }
        return $alerts;
    }

    private static function reminders(AlertContext $context): array
    {
        return $context->movements->where('include_alert', true)->sortByDesc('date')->map(fn ($movement) => AlertContext::alert(
            "reminder-{$movement->id}",
            'info',
            'bell',
            $movement->description,
            __('Reminder on :date: :amount.', ['date' => date_format_custom($movement->date), 'amount' => format_currency($movement->amount)]),
            __('See movement'),
            $context->movementsLink(search: $movement->description),
            0,
        ))->values()->all();
    }
}
