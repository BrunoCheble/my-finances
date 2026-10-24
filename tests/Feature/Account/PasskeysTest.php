<?php

namespace Tests\Feature\Account;

use App\Models\Passkey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Signing in with fingerprint / Face ID (passkeys) and the app's lock (ported from icd-laravel). The device is played by an EC P-256 key made
 * here, signing as a phone does, so the server's checks run for real (on the test database, see TestCase).
 */
class PasskeysTest extends TestCase
{
    use RefreshDatabase;

    private const ORIGIN = 'http://localhost';

    public function test_registering_a_device_asks_for_the_current_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/passkeys/options', ['password' => 'wrong'])->assertStatus(422);
        $this->actingAs($user)->postJson('/passkeys/options', ['password' => 'password'])
            ->assertOk()
            ->assertJsonPath('publicKey.rp.id', 'localhost')
            ->assertJsonPath('publicKey.authenticatorSelection.userVerification', 'required')
            ->assertJsonPath('publicKey.authenticatorSelection.residentKey', 'required')
            ->assertJsonPath('publicKey.attestation', 'none');
    }

    public function test_a_device_signs_in_with_its_signature(): void
    {
        [$user, $key] = $this->userWithPasskey();

        $this->postJson('/passkeys/sign-in', ['credential' => $this->assertion($user, $key, $this->challenge())])
            ->assertOk()
            ->assertJsonPath('redirect', route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertSame(1, Passkey::first()->sign_count);
        $this->assertNotNull(Passkey::first()->last_used_at);
    }

    public function test_a_wrong_signature_another_origin_or_an_old_challenge_does_not_sign_in(): void
    {
        [$user, $key] = $this->userWithPasskey();

        // Signed by another key.
        [, $other] = $this->keyPair();
        $this->postJson('/passkeys/sign-in', ['credential' => $this->assertion($user, $other, $this->challenge())])->assertStatus(422);
        // For another site.
        $this->postJson('/passkeys/sign-in', ['credential' => $this->assertion($user, $key, $this->challenge(), 'https://evil-localhost.com')])->assertStatus(422);
        // The same challenge a second time.
        $challenge = $this->challenge();
        $credential = $this->assertion($user, $key, $challenge);
        $this->postJson('/passkeys/sign-in', ['credential' => $credential])->assertOk();
        auth()->logout();
        $this->postJson('/passkeys/sign-in', ['credential' => $this->assertion($user, $key, $challenge, self::ORIGIN, 2)])->assertStatus(422);
    }

    public function test_unlocking_only_takes_the_users_own_devices(): void
    {
        [$user] = $this->userWithPasskey();
        [$other, $otherKey] = $this->userWithPasskey('other-device');

        $challenge = $this->actingAs($user)->postJson('/lock/options')->assertOk()->json('publicKey.challenge');
        $this->actingAs($user)->postJson('/lock/unlock', ['credential' => $this->assertion($other, $otherKey, $challenge, self::ORIGIN, 1, 'other-device')])
            ->assertStatus(422);
    }

    public function test_the_app_locks_after_15_minutes_without_use_and_unlocks_with_the_password(): void
    {
        [$user] = $this->userWithPasskey();
        $phone = ['User-Agent' => 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 Chrome/130.0 Mobile Safari/537.36'];
        $computer = ['User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_6) AppleWebKit/605.1.15 Version/18.0 Safari/605.1.15'];

        // (the wallets page: the dashboard's queries are MySQL only, the tests run on SQLite)
        // The same 15 minutes on a phone and on a computer.
        $this->actingAs($user)->withSession(['lock.last_activity' => time() - 14 * 60])->withHeaders($phone)->get('/wallets')->assertOk();
        $this->actingAs($user)->withSession(['lock.last_activity' => time() - 14 * 60])->withHeaders($computer)->get('/wallets')->assertOk();
        $this->actingAs($user)->withSession(['lock.last_activity' => time() - 16 * 60])->withHeaders($computer)->get('/wallets')->assertRedirect('/lock');
        $this->actingAs($user)->withSession(['lock.last_activity' => time() - 16 * 60])->withHeaders($phone)->get('/wallets')->assertRedirect('/lock');
        // A page's script gets 423 and covers the page with the lock.
        $this->actingAs($user)->withSession(['lock.last_activity' => time() - 16 * 60])->getJson('/api/financial-movements/suggestions?q=ab')->assertStatus(423);
        $this->actingAs($user)->withSession(['lock.locked' => true])->get('/lock')->assertOk()->assertSee(__('Escudo locked'));

        $this->actingAs($user)->withSession(['lock.locked' => true])->postJson('/lock/password', ['password' => 'wrong'])->assertStatus(422);
        $this->actingAs($user)->withSession(['lock.locked' => true])->postJson('/lock/password', ['password' => 'password'])->assertOk();
        $this->actingAs($user)->get('/wallets')->assertOk();
    }

    public function test_users_without_a_device_lock_too_and_unlock_with_the_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->withSession(['lock.last_activity' => time() - 16 * 60])->get('/dashboard')->assertRedirect('/lock');
        $this->actingAs($user)->withSession(['lock.locked' => true])->get('/lock')->assertOk()->assertSee(__('Hello, :name. Type your password to continue.', ['name' => $user->name]), false);
        $this->actingAs($user)->withSession(['lock.locked' => true])->postJson('/lock/password', ['password' => 'password'])->assertOk();
    }

    public function test_a_session_restored_by_remember_me_starts_locked(): void
    {
        [$user] = $this->userWithPasskey();
        $user->forceFill(['remember_token' => 'remember-token'])->save();
        $cookie = $user->id . '|remember-token|' . $user->getAuthPassword();

        $this->withCookie(auth()->guard()->getRecallerName(), $cookie)->get('/dashboard')->assertRedirect('/lock');
    }

    public function test_a_device_is_removed_only_by_its_owner(): void
    {
        [$user] = $this->userWithPasskey();
        [$other] = $this->userWithPasskey('other-device');
        $passkey = $user->passkeys()->first();

        $this->actingAs($other)->delete("/passkeys/{$passkey->id}")->assertNotFound();
        $this->actingAs($user)->delete("/passkeys/{$passkey->id}")->assertRedirect();
        $this->assertDatabaseMissing('passkeys', ['id' => $passkey->id]);
    }

    /** @return array{0: User, 1: \OpenSSLAsymmetricKey} */
    private function userWithPasskey(string $credential = 'this-device'): array
    {
        $user = User::factory()->create();
        [$publicKey, $privateKey] = $this->keyPair();
        $user->passkeys()->create(['name' => 'Phone', 'credential_id' => $this->base64Url($credential), 'public_key' => $publicKey, 'sign_count' => 0]);

        return [$user, $privateKey];
    }

    /** @return array{0: string, 1: \OpenSSLAsymmetricKey} */
    private function keyPair(): array
    {
        $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);

        return [openssl_pkey_get_details($key)['key'], $key];
    }

    private function challenge(): string
    {
        return $this->postJson('/passkeys/sign-in/options')->assertOk()->json('publicKey.challenge');
    }

    /** What a phone sends back: its data for this site, the browser's data and their signature by the passkey. */
    private function assertion(User $user, \OpenSSLAsymmetricKey $key, string $challenge, string $origin = self::ORIGIN, int $count = 1, string $credential = 'this-device'): array
    {
        $clientData = json_encode(['type' => 'webauthn.get', 'challenge' => $challenge, 'origin' => $origin]);
        // Site hash, flags "user present" and "user verified", signature count.
        $authenticatorData = hash('sha256', 'localhost', true) . chr(0x05) . pack('N', $count);
        openssl_sign($authenticatorData . hash('sha256', $clientData, true), $signature, $key, OPENSSL_ALGO_SHA256);

        return [
            'id' => $this->base64Url($credential),
            'clientDataJSON' => $this->base64Url($clientData),
            'authenticatorData' => $this->base64Url($authenticatorData),
            'signature' => $this->base64Url($signature),
            'userHandle' => $this->base64Url(hash_hmac('sha256', 'passkey-user:' . $user->id, (string) config('app.key'), true)),
        ];
    }

    private function base64Url(string $binary): string
    {
        return rtrim(strtr(base64_encode($binary), '+/', '-_'), '=');
    }
}
