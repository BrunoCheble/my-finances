<?php

use App\Models\User;
use App\Services\Auth\GoogleSignInService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.google.client_id' => 'client-123.apps.googleusercontent.com', 'services.google.client_secret' => 'secret']);
});

/** What Google's token endpoint answers: an ID token (unsigned here; it comes straight from Google over TLS). */
function googleAnswers(array $claims): void
{
    $claims += [
        'iss' => 'https://accounts.google.com', 'aud' => 'client-123.apps.googleusercontent.com',
        'exp' => time() + 300, 'sub' => 'google-sub-1', 'email' => 'bruno@example.com', 'email_verified' => true, 'name' => 'Bruno',
    ];
    Http::swap(new \Illuminate\Http\Client\Factory()); // one answer per call
    $part = fn ($data) => rtrim(strtr(base64_encode(json_encode($data)), '+/', '-_'), '=');
    Http::fake([GoogleSignInService::TOKEN_URL => Http::response([
        'access_token' => 'at', 'id_token' => $part(['alg' => 'RS256']) . '.' . $part($claims) . '.sig',
    ])]);
}

/** Goes to Google and comes back with a code; returns the callback response. */
function backFromGoogle($test, ?string $state = null)
{
    $location = $test->get(route('auth.google'))->assertRedirect()->headers->get('Location');
    parse_str(parse_url($location, PHP_URL_QUERY), $query);
    expect($location)->toStartWith(GoogleSignInService::AUTHORIZE_URL)
        ->and($query['code_challenge_method'])->toBe('S256')
        ->and($query['redirect_uri'])->toBe(route('auth.google.callback'));

    return $test->get(route('auth.google.callback', ['code' => 'the-code', 'state' => $state ?? $query['state']]));
}

it('signs in an existing user by the verified email and links the Google account', function () {
    $user = User::factory()->create(['email' => 'Bruno@Example.com']);
    googleAnswers([]);

    backFromGoogle($this)->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->google_id)->toBe('google-sub-1');
    Http::assertSent(fn ($request) => $request->url() === GoogleSignInService::TOKEN_URL && filled($request['code_verifier']));
});

it('does not create an account for an unknown email', function () {
    googleAnswers(['email' => 'someone@else.com']);

    backFromGoogle($this)->assertRedirect(route('login'))->assertSessionHas('error');

    $this->assertGuest();
    expect(User::count())->toBe(0);
});

it('refuses an unverified email, a forged state and a token for another app', function () {
    User::factory()->create(['email' => 'bruno@example.com']);

    googleAnswers(['email_verified' => false]);
    backFromGoogle($this)->assertSessionHas('error');
    $this->assertGuest();

    googleAnswers([]);
    backFromGoogle($this, 'forged-state')->assertSessionHas('error');
    $this->assertGuest();

    googleAnswers(['aud' => 'another-client']);
    backFromGoogle($this)->assertSessionHas('error');
    $this->assertGuest();
});

it('links and unlinks Google from the profile, never to two users', function () {
    $user = User::factory()->create(['email' => 'me@example.com']);
    User::factory()->create(['email' => 'other@example.com', 'google_id' => 'google-sub-1']);
    $this->actingAs($user);

    googleAnswers(['email' => 'me@gmail.com']);
    backFromGoogle($this)->assertRedirect(route('profile.edit'))->assertSessionHas('error');
    expect($user->fresh()->google_id)->toBeNull();

    googleAnswers(['sub' => 'google-sub-2', 'email' => 'me@gmail.com']);
    backFromGoogle($this)->assertRedirect(route('profile.edit'))->assertSessionHas('success');
    expect($user->fresh()->google_id)->toBe('google-sub-2');

    $this->delete(route('auth.google.unlink'))->assertRedirect(route('profile.edit'));
    expect($user->fresh()->google_id)->toBeNull();
});

it('hides Google when it is not configured', function () {
    config(['services.google.client_id' => null]);

    $this->get(route('login'))->assertOk()->assertDontSee(__('Continue with Google'));
    $this->get(route('auth.google'))->assertNotFound();
});
