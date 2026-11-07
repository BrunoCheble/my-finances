<?php

namespace App\Http\Controllers;

use App\Models\PaymentReminder;
use App\Models\PushSubscription;
use App\Services\Cache\DashboardCacheService;
use App\Services\Push\PaymentReminderNotifier;
use App\Services\Push\WebPush;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PaymentReminderController extends Controller
{
    public function index(): View
    {
        return view('payment-reminders.index', [
            'reminders' => PaymentReminder::orderBy('day')->orderBy('name')->get(),
            'month' => now()->format('Y-m'),
            'vapidKey' => WebPush::publicKey(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        PaymentReminder::create($this->validated($request));
        DashboardCacheService::clearAllByUser();

        return redirect()->route('payment-reminders.index')->with('success', __('Reminder created.'));
    }

    public function update(Request $request, PaymentReminder $paymentReminder): RedirectResponse
    {
        $paymentReminder->update($this->validated($request));
        DashboardCacheService::clearAllByUser();

        return redirect()->route('payment-reminders.index')->with('success', __('Reminder updated.'));
    }

    /** Marks (or unmarks) the current month as paid by hand: the bank's text didn't have the reference. */
    public function paid(PaymentReminder $paymentReminder): RedirectResponse
    {
        $month = now()->format('Y-m');
        $paymentReminder->update(['paid_month' => $paymentReminder->paid_month === $month ? null : $month]);
        DashboardCacheService::clearAllByUser();

        return redirect()->route('payment-reminders.index');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'day' => 'required|integer|min:1|max:31',
            'amount' => 'nullable|numeric|min:0',
            'reference' => 'nullable|string|max:255',
        ]);
    }

    public function destroy(PaymentReminder $paymentReminder): RedirectResponse
    {
        $paymentReminder->delete();
        DashboardCacheService::clearAllByUser();

        return redirect()->route('payment-reminders.index')->with('success', __('Reminder removed.'));
    }

    /** This browser/device accepted notifications. */
    public function subscribe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => 'required|url|max:500',
            'keys.p256dh' => 'required|string',
            'keys.auth' => 'required|string',
        ]);
        PushSubscription::withoutGlobalScopes()->updateOrCreate(
            ['endpoint' => $data['endpoint']],
            ['user_id' => auth()->id(), 'p256dh' => $data['keys']['p256dh'], 'auth' => $data['keys']['auth']],
        );

        return response()->json(['saved' => true]);
    }

    public function test(): JsonResponse
    {
        $sent = WebPush::sendToUser(auth()->id(), [
            'title' => __('Escudo notifications are on'),
            'body' => __('You will be notified on the morning of each payment day.'),
            'url' => route('payment-reminders.index'),
        ]);

        return response()->json(['sent' => $sent]);
    }

    /**
     * Called by the scheduled trigger (GitHub Actions) a few times each morning: sends what is due
     * today, once. Public on purpose, it does nothing more than that.
     */
    public function run(): JsonResponse
    {
        return response()->json(['sent' => PaymentReminderNotifier::run()]);
    }
}
