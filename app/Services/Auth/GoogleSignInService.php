<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * "Sign in with Google" with OpenID Connect, without extra packages (the server has no Composer): the
 * authorization code flow with state and PKCE, the code exchanged by the server and the ID token read from that
 * direct answer of Google (TLS stands for its signature, OIDC Core 3.1.3.7), then checked: issuer, audience,
 * expiry and a verified email. Only users that already exist sign in; nobody is created.
 */
class GoogleSignInService
{
    public const AUTHORIZE_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    public const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const ISSUERS = ['https://accounts.google.com', 'accounts.google.com'];
    private const SESSION = 'google_sign_in';

    public static function configured(): bool
    {
        return filled(config('services.google.client_id')) && filled(config('services.google.client_secret'));
    }

    /** Where Google sends the user back (must be listed in the Google Cloud client). */
    public static function redirectUri(): string
    {
        return route('auth.google.callback');
    }

    /** The address of Google's consent screen; $linking: from the profile, to link the Google account. */
    public function authorizationUrl(Request $request, bool $linking = false): string
    {
        $state = Str::random(40);
        $verifier = Str::random(64);
        $request->session()->put(self::SESSION, ['state' => $state, 'verifier' => $verifier, 'linking' => $linking, 'at' => time()]);

        return self::AUTHORIZE_URL . '?' . http_build_query([
            'client_id' => config('services.google.client_id'),
            'redirect_uri' => self::redirectUri(),
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256',
            'prompt' => 'select_account',
        ]);
    }

    /**
     * Google's answer: the identity it vouches for. Returns [claims, linking].
     *
     * @return array{0: array{sub: string, email: string, name: ?string}, 1: bool}
     */
    public function identity(Request $request): array
    {
        $kept = $request->session()->pull(self::SESSION);
        $state = (string) $request->query('state');
        if (! is_array($kept) || $state === '' || ! hash_equals($kept['state'], $state) || time() - ($kept['at'] ?? 0) > 600) {
            throw self::failure(__('The Google sign-in expired. Try again.'));
        }
        if ($request->filled('error') || ! $request->filled('code')) {
            throw self::failure(__('Sign-in with Google was cancelled.'));
        }

        $response = Http::asForm()->timeout(15)->post(self::TOKEN_URL, [
            'code' => (string) $request->query('code'),
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'redirect_uri' => self::redirectUri(),
            'grant_type' => 'authorization_code',
            'code_verifier' => $kept['verifier'],
        ]);
        $claims = $response->successful() ? self::claims((string) $response->json('id_token')) : null;

        if (! $claims
            || ! in_array($claims['iss'] ?? null, self::ISSUERS, true)
            || ($claims['aud'] ?? null) !== config('services.google.client_id')
            || ($claims['exp'] ?? 0) < time()
            || empty($claims['sub'])) {
            throw self::failure();
        }
        if (empty($claims['email']) || ($claims['email_verified'] ?? false) !== true) {
            throw self::failure(__('Your Google account email is not verified.'));
        }

        return [['sub' => (string) $claims['sub'], 'email' => mb_strtolower($claims['email']), 'name' => $claims['name'] ?? null], (bool) $kept['linking']];
    }

    /** The user of an identity: linked before, or the existing user with that email (then linked). */
    public function userFor(array $identity): User
    {
        $user = User::where('google_id', $identity['sub'])->first()
            ?? User::whereRaw('LOWER(email) = ?', [$identity['email']])->first();
        if (! $user) {
            throw self::failure(__('There is no Escudo account with :email.', ['email' => $identity['email']]));
        }
        if ($user->google_id !== null && $user->google_id !== $identity['sub']) {
            throw self::failure(__('This account is linked to another Google account.'));
        }
        $this->link($user, $identity);

        return $user;
    }

    public function link(User $user, array $identity): void
    {
        $owner = User::where('google_id', $identity['sub'])->first();
        if ($owner && $owner->isNot($user)) {
            throw self::failure(__('This Google account is linked to another user.'));
        }
        $user->forceFill(['google_id' => $identity['sub']])->save();
    }

    /** The payload of the ID token (base64url JSON), null when malformed. */
    private static function claims(string $jwt): ?array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            return null;
        }
        $json = base64_decode(strtr($parts[1], '-_', '+/'), true);
        $claims = $json === false ? null : json_decode($json, true);

        return is_array($claims) ? $claims : null;
    }

    public static function failure(?string $message = null): ValidationException
    {
        return ValidationException::withMessages(['google' => $message ?? __('Google could not confirm who you are. Try again.')]);
    }
}
