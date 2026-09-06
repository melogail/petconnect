<?php

namespace App\Http\Requests\Profile;

use App\Concerns\PasswordValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Confirming an account deletion.
 *
 * One field, and it is the whole security control: `current_password` checks
 * the supplied password against the authenticated user's hash, so a deletion
 * cannot be triggered by a borrowed session, a stale tab or a cross-site form
 * post that guessed the route.
 *
 * The legacy destroy required nothing — `$user->delete(); auth()->logout();`
 * with a `// TODO::Send an email to verify the account deletion.` above it —
 * against a `users` table with no soft deletes and eight cascading foreign
 * keys. One request with no confirmation removed the account, its listings, its
 * comments and its messages permanently.
 *
 * The rest of the control is outside this class and named here so the whole
 * arrangement is readable in one place: Settings\ProfileController::destroy
 * authorizes through UserPolicy::delete, runs Actions\Profiles\DeleteUserAccount
 * (which clears the polymorphic rows the cascade would strand), and then logs
 * out, invalidates the session and regenerates the CSRF token.
 */
class DeleteProfileRequest extends FormRequest
{
    use PasswordValidationRules;

    /**
     * @return array<string, array<int, Password|ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        return [
            'password' => $this->currentPasswordRules(),
        ];
    }
}
