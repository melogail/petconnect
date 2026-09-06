<?php

namespace App\Http\Requests\Settings;

use App\Concerns\PasswordValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The password change on `settings/security`.
 *
 * Rules live in App\Concerns\PasswordValidationRules, which registration and
 * the forgotten-password reset validate through as well, so the strength rule
 * cannot drift between the three ways a password gets set.
 *
 * No authorize(): .ai/rules/controllers.md puts authorization in the
 * controller, and .ai/rules/controllers.md exempts the Settings controllers
 * from a policy — they act on `$request->user()` and name no second party.
 * `current_password` is what stands in for one, so a borrowed session cannot
 * change the credential it borrowed.
 *
 * The error bag is the default one. Fortify's published stub validates with
 * `validateWithBag('updatePassword')`; this endpoint is not Fortify's (see
 * Actions\Profiles\UpdatePassword) and `resources/js/pages/settings/Security.vue`
 * reads `errors.current_password`, so moving the bag would silently stop the
 * "wrong current password" message rendering.
 *
 * `newPassword()` is what keeps the controller thin. It reads `validated()`,
 * which is the point: the controller used to reach for `$request->password`
 * and `Illuminate\Http\Request::__get` answers out of the **raw** input bag,
 * so it hands over whatever arrived under that key whether a rule matched it
 * or not. Correct today only because a rule happens to exist.
 */
class PasswordUpdateRequest extends FormRequest
{
    use PasswordValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'current_password' => $this->currentPasswordRules(),
            'password' => $this->passwordRules(),
        ];
    }

    /**
     * The plain-text password the account is being moved to.
     *
     * `newPassword()` rather than `password()`, both because the bag carries
     * two passwords and only one of them is this one, and because a bare
     * `password()` on a FormRequest is the shape that bit
     * UpdateProfileRequest::uploadedImage() — `Illuminate\Http\Request` is free
     * to add a method of any common name, and an incompatible override is a
     * fatal at class load rather than one broken endpoint. Nothing declares
     * `password()` today; the name is avoided rather than defended.
     */
    public function newPassword(): string
    {
        return (string) $this->validated('password');
    }
}
