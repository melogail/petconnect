<?php

namespace App\Actions\Profiles;

use App\Models\User;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Support\Facades\Password;

/**
 * Change an account's password, and kill anything that could still change it
 * back.
 *
 * ## Why this is an Action and not two lines in the controller
 *
 * Settings\SecurityController::update used to run the write itself —
 * `$request->user()->update(['password' => $request->password])` — which is a
 * business write in a controller (.ai/rules/app.md) and read the value off the
 * raw input bag through `Illuminate\Http\Request::__get` rather than
 * `validated()`. That is safe only for as long as PasswordUpdateRequest happens
 * to have a rule for the key; the day a rule is renamed, `__get` keeps handing
 * over whatever arrived. The value now comes from
 * PasswordUpdateRequest::newPassword(), which reads `validated()`.
 *
 * A single unit of work with no sequence, so an Action rather than a pipeline.
 *
 * ## The reset-token delete is the part that was missing
 *
 * This is **not** Fortify's flow, despite the route name. `UpdateUserPassword`
 * does not exist in laravel/fortify 1.39 — it was only ever a starter-kit stub
 * — only the marker contract `UpdatesUserPasswords` ships, nothing binds it,
 * and `config/fortify.php` does not enable `Features::updatePasswords()`, so
 * Fortify's `PUT /user/password` route is never registered. `settings/password`
 * (named `user-password.update`) is entirely this application's.
 *
 * What Fortify's PasswordController does that this application did not is
 * `$this->broker()->deleteToken($request->user())`. Nothing anywhere in this
 * codebase called `deleteToken`, so a user who asked for a reset link and then
 * changed their password from the settings page left the emailed link live for
 * the whole of `auth.passwords.users.expire` — a credential that outlives the
 * credential it replaces, held in a mailbox that may be exactly what prompted
 * the change. Deleting the token here closes that.
 *
 * The broker is resolved by `config('fortify.passwords')` — the same key
 * Fortify's controller reads — so the two cannot pick different brokers.
 *
 * ## What it deliberately does not do
 *
 * No `PasswordUpdatedViaController` event: that event belongs to Fortify's
 * controller, which never runs here, and nothing in the application listens for
 * it. Nor does it log other sessions out — that is a separate decision with its
 * own UI, not something to smuggle into a bug fix.
 *
 * Authorization is not here either. .ai/rules/controllers.md exempts the
 * Settings controllers from a policy (they act on the authenticated user and
 * name no second party), and route middleware — `auth`, `verified`,
 * `throttle:6,1` — plus the request's `current_password` rule are what stand in
 * front of it.
 */
class UpdatePassword
{
    /**
     * Set a new password and invalidate any outstanding reset token.
     *
     * `$password` is the plain-text value: `User::$casts` declares `password`
     * as `hashed`, so the model hashes it on the way to the column and hashing
     * here would double-hash it.
     *
     * `forceFill()` rather than `update()`, matching Actions\Fortify\
     * ResetUserPassword and Actions\Profiles\ApplyUserLocale: `password` is in
     * User's #[Fillable] today, but a credential write should not stop working
     * the day somebody decides it should not be mass assignable.
     *
     * The broker is annotated as the concrete Illuminate\Auth\Passwords\
     * PasswordBroker because `deleteToken()` is on the class and **not** on the
     * Illuminate\Contracts\Auth\PasswordBroker interface that
     * `Password::broker()` is documented to return — the same unchecked call
     * Fortify's own PasswordController makes against a contract-typed variable.
     */
    public function handle(User $user, string $password): void
    {
        $user->forceFill(['password' => $password])->save();

        /** @var PasswordBroker $broker */
        $broker = Password::broker(config('fortify.passwords'));

        $broker->deleteToken($user);
    }
}
