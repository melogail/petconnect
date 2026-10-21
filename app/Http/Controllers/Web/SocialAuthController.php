<?php

namespace App\Http\Controllers\Web;

use App\Actions\Auth\ResolveSocialUser;
use App\Enums\SocialProvider;
use App\Http\Controllers\Controller;
use GuzzleHttp\Exception\TransferException;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

/**
 * "Continue with Google" / "Continue with Facebook".
 *
 * Both actions are GETs, and `callback` writes — a new user, a new link, a
 * session. That is the one place .ai/rules/controllers.md's "a GET never
 * writes" does not reach: an OAuth callback is a top-level browser redirect
 * from the provider, not an Inertia visit, and OAuth 2.0 fixes the method.
 * Neither URL is ever rendered as an Inertia `<Link>` — the buttons are plain
 * anchors, so nothing prefetches them (SocialLoginButtons.vue).
 *
 * `{provider}` binds to App\Enums\SocialProvider, so an unknown name is a
 * 404 before this runs; a known provider with no credentials in this
 * environment is the same 404, because the buttons are not shown for it and
 * a hand-typed URL should not reach Socialite's "missing client id" error.
 *
 * No policy-governed model is acted on, so there is no `$this->authorize()`.
 */
class SocialAuthController extends Controller
{
    /**
     * Send the browser to the provider's consent screen.
     */
    public function redirect(SocialProvider $provider): SymfonyRedirectResponse
    {
        abort_unless($provider->isConfigured(), 404);

        return Socialite::driver($provider->value)->redirect();
    }

    /**
     * Receive the provider's answer and sign the member in.
     *
     * A denied consent screen, a tampered `state` or a provider outage all
     * land on the login page with a neutral explanation rather than a 500.
     * Programmer errors are not caught.
     */
    public function callback(Request $request, SocialProvider $provider, ResolveSocialUser $resolveSocialUser): RedirectResponse
    {
        abort_unless($provider->isConfigured(), 404);

        try {
            $identity = Socialite::driver($provider->value)->user();
        } catch (InvalidStateException|TransferException) {
            return $this->backToLogin(__('We could not sign you in with :provider. Please try again.', ['provider' => $provider->label()]));
        }

        try {
            $user = $resolveSocialUser->handle($provider, $identity);
        } catch (ValidationException $exception) {
            return $this->backToLogin($exception->validator->errors()->first());
        }

        if ($user->wasRecentlyCreated) {
            event(new Registered($user));
        }

        Auth::login($user, remember: true);

        $request->session()->regenerate();

        return redirect()->intended(config('fortify.home'));
    }

    /**
     * The login page carries a `status` slot (StatusMessage.vue) already used
     * for "your account has been deactivated"; a failed social sign-in is the
     * same kind of neutral explanation.
     */
    protected function backToLogin(string $status): RedirectResponse
    {
        return redirect()->route('login')->with('status', $status);
    }
}
