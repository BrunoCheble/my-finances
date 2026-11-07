<?php

use App\Models\PaymentReminder;
use App\Models\PushSubscription;
use App\Models\User;
use App\Services\Push\PaymentReminderNotifier;
use App\Services\Push\WebPush;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

function subscribe(): PushSubscription
{
    $device = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
    $ec = openssl_pkey_get_details($device)['ec'];
    return PushSubscription::create([
        'endpoint' => 'https://push.example.com/send/' . uniqid(),
        'p256dh' => WebPush::base64url("\x04" . str_pad($ec['x'], 32, "\0", STR_PAD_LEFT) . str_pad($ec['y'], 32, "\0", STR_PAD_LEFT)),
        'auth' => WebPush::base64url(random_bytes(16)),
    ]);
}

it('creates, lists and removes reminders of the user', function () {
    $this->post(route('payment-reminders.store'), ['name' => 'Renda', 'day' => 8, 'amount' => '750'])->assertRedirect();
    $reminder = PaymentReminder::first();
    expect($reminder->day)->toBe(8);

    $this->get(route('payment-reminders.index'))->assertOk()->assertSee('Renda');

    $other = User::factory()->create();
    $this->actingAs($other)->delete(route('payment-reminders.destroy', $reminder))->assertNotFound();
    $this->actingAs($this->user)->delete(route('payment-reminders.destroy', $reminder))->assertRedirect();
    expect(PaymentReminder::count())->toBe(0);
});

it('saves the push subscription of the device', function () {
    $this->postJson(route('push-subscriptions.store'), [
        'endpoint' => 'https://push.example.com/abc', 'keys' => ['p256dh' => 'key', 'auth' => 'secret'],
    ])->assertOk();
    $this->postJson(route('push-subscriptions.store'), [
        'endpoint' => 'https://push.example.com/abc', 'keys' => ['p256dh' => 'key2', 'auth' => 'secret'],
    ])->assertOk();

    expect(PushSubscription::count())->toBe(1)->and(PushSubscription::first()->p256dh)->toBe('key2');
});

it('sends the payments due today once, from 9h on', function () {
    Http::fake(['push.example.com/*' => Http::response('', 201)]);
    subscribe();
    PaymentReminder::create(['name' => 'Renda', 'day' => 8, 'amount' => 750]);
    PaymentReminder::create(['name' => 'Seguro', 'day' => 9]);

    expect(PaymentReminderNotifier::run(Carbon::parse('2026-11-08 08:30')))->toBe(0);
    expect(PaymentReminderNotifier::run(Carbon::parse('2026-11-08 09:05')))->toBe(1);
    expect(PaymentReminderNotifier::run(Carbon::parse('2026-11-08 10:05')))->toBe(0);
    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => $request->hasHeader('Content-Encoding', 'aes128gcm')
        && str_starts_with($request->header('Authorization')[0], 'vapid t='));

    // next month again
    expect(PaymentReminderNotifier::run(Carbon::parse('2026-12-08 09:05')))->toBe(1);
});

it('sends a day the month does not have on its last day', function () {
    Http::fake(['push.example.com/*' => Http::response('', 201)]);
    subscribe();
    PaymentReminder::create(['name' => 'Cartão', 'day' => 31]);

    expect(PaymentReminderNotifier::run(Carbon::parse('2026-11-29 09:05')))->toBe(0);
    expect(PaymentReminderNotifier::run(Carbon::parse('2026-11-30 09:05')))->toBe(1);
});

it('removes a subscription the push service no longer knows', function () {
    Http::fake(['push.example.com/*' => Http::response('', 410)]);
    subscribe();
    PaymentReminder::create(['name' => 'Renda', 'day' => 8]);

    PaymentReminderNotifier::run(Carbon::parse('2026-11-08 09:05'));
    expect(PushSubscription::count())->toBe(0);
});

it('runs from the public trigger', function () {
    auth()->logout();
    $this->postJson(route('payment-reminders.run'))->assertOk()->assertJson(['sent' => 0]);
});

it('encrypts the payload so that only the device can read it (RFC 8291)', function () {
    $device = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
    $ec = openssl_pkey_get_details($device)['ec'];
    $devicePublic = "\x04" . str_pad($ec['x'], 32, "\0", STR_PAD_LEFT) . str_pad($ec['y'], 32, "\0", STR_PAD_LEFT);
    $auth = random_bytes(16);

    $body = WebPush::encrypt('{"title":"Renda"}', WebPush::base64url($devicePublic), WebPush::base64url($auth));

    // what the browser does
    $salt = substr($body, 0, 16);
    $keyLength = ord($body[20]);
    $serverPublic = substr($body, 21, $keyLength);
    $cipher = substr($body, 21 + $keyLength);
    $pem = "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode(hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . $serverPublic), 64, "\n") . "-----END PUBLIC KEY-----\n";
    $shared = openssl_pkey_derive(openssl_pkey_get_public($pem), $device, 32);
    $ikm = hash_hkdf('sha256', $shared, 32, "WebPush: info\0" . $devicePublic . $serverPublic, $auth);
    $cek = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $salt);
    $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $salt);
    $plain = openssl_decrypt(substr($cipher, 0, -16), 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, substr($cipher, -16));

    expect($plain)->toBe("{\"title\":\"Renda\"}\x02");
});

function movement(string $description, string $date, float $amount = -750): void
{
    $wallet = App\Models\Wallet::firstOrCreate(['name' => 'Banco'], ['type' => 'debit', 'active' => true]);
    test()->post(route('financial-movements.store'), [
        'description' => $description, 'date' => $date, 'wallet_id' => $wallet->id,
        'amount' => abs($amount), 'type' => App\Enums\FinancialMovementType::EXPENSE,
    ], ['Accept' => 'application/json'])->assertCreated();
}

it('is paid when a movement of the month has the reference as a word', function () {
    $reminder = PaymentReminder::create(['name' => 'Renda', 'day' => 8, 'reference' => 'renda']);
    movement('Prendas de Natal', '2026-11-03', -40);
    expect($reminder->paymentIn('2026-11'))->toBeNull();

    movement('DD Débito RENDA-NOV', '2026-11-07');
    expect($reminder->paymentIn('2026-11')['movement']->description)->toBe('DD Débito RENDA-NOV')
        ->and($reminder->paymentIn('2026-12'))->toBeNull();
});

it('does not push a reminder already paid', function () {
    Http::fake(['push.example.com/*' => Http::response('', 201)]);
    subscribe();
    PaymentReminder::create(['name' => 'Renda', 'day' => 8, 'reference' => 'RENDA']);
    movement('Renda novembro', '2026-11-06');

    expect(PaymentReminderNotifier::run(Carbon::parse('2026-11-08 09:05')))->toBe(0);
    Http::assertNothingSent();
});

it('is marked and unmarked as paid by hand for the current month', function () {
    $reminder = PaymentReminder::create(['name' => 'Seguro', 'day' => 3]);
    $this->patch(route('payment-reminders.paid', $reminder))->assertRedirect();
    expect($reminder->fresh()->paymentIn(now()->format('Y-m'))['manual'])->toBeTrue();

    $this->patch(route('payment-reminders.paid', $reminder));
    expect($reminder->fresh()->paymentIn(now()->format('Y-m')))->toBeNull();
});

it('updates the reminder', function () {
    $reminder = PaymentReminder::create(['name' => 'Renda', 'day' => 8]);
    $this->put(route('payment-reminders.update', $reminder), ['name' => 'Renda casa', 'day' => 9, 'reference' => 'RENDA'])->assertRedirect();
    expect($reminder->fresh()->only('name', 'day', 'reference'))->toBe(['name' => 'Renda casa', 'day' => 9, 'reference' => 'RENDA']);
});

it('alerts a payment of this month past its day and not paid', function () {
    Carbon::setTestNow('2026-11-10 12:00');
    $renda = PaymentReminder::create(['name' => 'Renda', 'day' => 8, 'reference' => 'RENDA']);
    $renda->forceFill(['created_at' => '2026-11-01'])->save();
    $later = PaymentReminder::create(['name' => 'Ginásio', 'day' => 20]);

    $keys = fn () => collect(App\Services\Alerts\AlertService::for('2026-11'))->pluck('key');
    expect($keys())->toContain("unpaid-{$renda->id}")->not->toContain("unpaid-{$later->id}");

    movement('Renda novembro', '2026-11-09');
    expect($keys())->not->toContain("unpaid-{$renda->id}");
    expect(collect(App\Services\Alerts\AlertService::for('2026-10'))->pluck('key'))->not->toContain("unpaid-{$renda->id}");
    Carbon::setTestNow();
});
