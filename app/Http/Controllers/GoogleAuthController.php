<?php

namespace App\Http\Controllers;

use App\Services\Auth\GoogleSignInService;
use App\Services\Passkeys\SessionLock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * "Sign in with Google" (see GoogleSignInService): from the login page, or from the profile to link the account.
 */
class GoogleAuthController extends Controller
{
    public function redirect(Request $request, GoogleSignInService $google): RedirectResponse
    {
        abort_unless(GoogleSignInService::configured(), 404);

        return redirect()->away($google->authorizationUrl($request, $request->user() !== null));
    }

    public function callback(Request $request, GoogleSignInService $google, SessionLock $lock): RedirectResponse
    {
        abort_unless(GoogleSignInService::configured(), 404);

        try {
            [$identity, $linking] = $google->identity($request);

            // from the profile: link the Google account to the signed-in user
            if ($linking && $request->user()) {
                $google->link($request->user(), $identity);
                return redirect()->route('profile.edit')->with('success', __('Google account linked.'));
            }

            $user = $google->userFor($identity);
        } catch (ValidationException $e) {
            $back = $request->user() ? route('profile.edit') : route('login');
            return redirect($back)->with('error', collect($e->errors())->flatten()->first());
        }

        Auth::login($user, true);
        $request->session()->regenerate();
        $lock->unlock($request);

        return redirect()->intended(route('dashboard'));
    }

    public function unlink(Request $request): RedirectResponse
    {
        $request->user()->forceFill(['google_id' => null])->save();

        return redirect()->route('profile.edit')->with('success', __('Google account unlinked.'));
    }
}
