<?php

namespace App\Services\Push;

use App\Models\PushSubscription;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Web Push (RFC 8291 payload encryption + RFC 8292 VAPID) with plain openssl: the server can't install
 * libraries. The VAPID key pair is created on first use and kept in storage (it never changes after that,
 * or the existing subscriptions stop working).
 */
class WebPush
{
    /** DER prefix of a P-256 public key (SubjectPublicKeyInfo) before its 65 raw bytes */
    private const P256_SPKI_PREFIX = '3059301306072a8648ce3d020106082a8648ce3d030107034200';

    public static function keyPath(): string
    {
        return storage_path('app/vapid.pem');
    }

    /** The public key the browser subscribes with (base64url, raw uncompressed point). */
    public static function publicKey(): string
    {
        return self::base64url(self::rawPublicKey(self::privateKey()));
    }

    /**
     * Sends a JSON payload to every subscription of the user; a subscription the push service no longer
     * knows (404/410) is removed. Returns how many were delivered.
     */
    public static function sendToUser(int $userId, array $payload): int
    {
        $sent = 0;
        foreach (PushSubscription::withoutGlobalScopes()->where('user_id', $userId)->get() as $subscription) {
            $status = self::send($subscription, json_encode($payload));
            if (in_array($status, [404, 410])) {
                $subscription->delete();
            } elseif ($status >= 200 && $status < 300) {
                $sent++;
            } else {
                Log::warning('Web push failed', ['status' => $status, 'subscription' => $subscription->id]);
            }
        }
        return $sent;
    }

    public static function send(PushSubscription $subscription, string $payload): int
    {
        $parts = parse_url($subscription->endpoint);
        $audience = $parts['scheme'] . '://' . $parts['host'];
        $key = self::privateKey();

        $response = Http::withHeaders([
            'Authorization' => 'vapid t=' . self::jwt($audience, $key) . ', k=' . self::publicKey(),
            'Content-Encoding' => 'aes128gcm',
            'TTL' => '86400',
            'Urgency' => 'normal',
        ])->withBody(self::encrypt($payload, $subscription->p256dh, $subscription->auth), 'application/octet-stream')
            ->timeout(15)
            ->post($subscription->endpoint);

        return $response->status();
    }

    /**
     * aes128gcm content coding of the payload for one subscription (RFC 8291).
     */
    public static function encrypt(string $payload, string $p256dh, string $auth): string
    {
        $userPublic = self::base64urlDecode($p256dh);
        $authSecret = self::base64urlDecode($auth);

        $local = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        $localPublic = self::rawPublicKey($local);
        $shared = openssl_pkey_derive(self::publicKeyFromRaw($userPublic), $local, 32);

        $ikm = hash_hkdf('sha256', $shared, 32, "WebPush: info\0" . $userPublic . $localPublic, $authSecret);
        $salt = random_bytes(16);
        $cek = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $salt);
        $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $salt);

        // a single record: the payload and the last-record delimiter
        $cipher = openssl_encrypt($payload . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag);

        return $salt . pack('N', 4096) . chr(strlen($localPublic)) . $localPublic . $cipher . $tag;
    }

    private static function jwt(string $audience, $key): string
    {
        $header = self::base64url(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $claims = self::base64url(json_encode([
            'aud' => $audience,
            'exp' => time() + 12 * 3600,
            'sub' => 'mailto:noreply@' . (parse_url(config('app.url'), PHP_URL_HOST) ?: 'localhost'),
        ]));
        openssl_sign("$header.$claims", $der, $key, OPENSSL_ALGO_SHA256);

        return "$header.$claims." . self::base64url(self::derToRaw($der));
    }

    private static function privateKey()
    {
        $path = self::keyPath();
        if (!is_file($path)) {
            $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
            openssl_pkey_export($key, $pem);
            file_put_contents($path, $pem);
            @chmod($path, 0600);
        }
        return openssl_pkey_get_private(file_get_contents($path));
    }

    private static function rawPublicKey($key): string
    {
        $ec = openssl_pkey_get_details($key)['ec'];
        return "\x04" . str_pad($ec['x'], 32, "\0", STR_PAD_LEFT) . str_pad($ec['y'], 32, "\0", STR_PAD_LEFT);
    }

    private static function publicKeyFromRaw(string $raw)
    {
        $der = hex2bin(self::P256_SPKI_PREFIX) . $raw;
        $pem = "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
        return openssl_pkey_get_public($pem);
    }

    /** ECDSA signature: DER (what openssl gives) to r||s (what JWT expects). */
    private static function derToRaw(string $der): string
    {
        $offset = 2;
        $raw = '';
        for ($i = 0; $i < 2; $i++) {
            $length = ord($der[$offset + 1]);
            $int = ltrim(substr($der, $offset + 2, $length), "\0");
            $raw .= str_pad($int, 32, "\0", STR_PAD_LEFT);
            $offset += 2 + $length;
        }
        return $raw;
    }

    public static function base64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public static function base64urlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
