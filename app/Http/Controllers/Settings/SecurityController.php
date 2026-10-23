<?php

namespace App\Http\Controllers\Settings;

use App\Actions\Profiles\UpdatePassword;
use App\Concerns\ResolvesRequestUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PasswordUpdateRequest;
use App\Http\Requests\Settings\TwoFactorAuthenticationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

class SecurityController extends Controller
{
    use ResolvesRequestUser;

    /**
     * Show the user's security settings page.
     */
    public function edit(TwoFactorAuthenticationRequest $request): Response
    {
        $props = [
            'canManageTwoFactor' => Features::canManageTwoFactorAuthentication(),
            'canManagePasskeys'  => Features::canManagePasskeys(),
            'passkeys'           => Features::canManagePasskeys()
                ? $this->actor($request)
                    ->passkeys()
                    ->select(['id', 'name', 'credential', 'created_at', 'last_used_at'])
                    ->latest()
                    ->get()
                    ->map(fn ($passkey) => [
                        'id'                => $passkey->id,
                        'name'              => $passkey->name,
                        'authenticator'     => $passkey->authenticator,
                        'created_at_diff'   => $passkey->created_at->diffForHumans(),
                        'last_used_at_diff' => $passkey->last_used_at?->diffForHumans(),
                    ])
                    ->values()
                    ->all()
                : [],
            'passwordRules'      => Password::defaults()->toPasswordRulesString(),
        ];

        if (Features::canManageTwoFactorAuthentication()) {
            $request->ensureStateIsValid();

            $props['twoFactorEnabled']     = $this->actor($request)->hasEnabledTwoFactorAuthentication();
            $props['requiresConfirmation'] = Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm');
        }

        return Inertia::render('settings/Security', $props);
    }

    /**
     * Update the user's password.
     *
     * The write is Actions\Profiles\UpdatePassword's, not this method's: it
     * used to run `$request->user()->update([...])` here, which is a business
     * write in a controller, and read the value off the raw input bag through
     * `Request::__get` rather than `validated()`. The Action also deletes any
     * outstanding password reset token, which nothing in this application did
     * — read its docblock for why that gap mattered and why this endpoint is
     * not Fortify's despite the route name.
     */
    public function update(PasswordUpdateRequest $request, UpdatePassword $updatePassword): RedirectResponse
    {
        $updatePassword->handle($this->actor($request), $request->newPassword());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Password updated.')]);

        return back();
    }
}
