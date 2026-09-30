<?php

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The fields a token registration carries beyond what Fortify's
 * CreateNewUser validates itself: only the device the token is minted for.
 * Name, email and password are validated by CreateNewUser so the two sign-up
 * paths can never drift apart.
 */
class RegisterRequest extends FormRequest
{
    /**
     * @return array<string, list<ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'device_name' => ['required', 'string', 'max:255'],
        ];
    }

    public function deviceName(): string
    {
        return (string) $this->validated('device_name');
    }

    /**
     * @return array<string, mixed>
     */
    public function accountInput(): array
    {
        return $this->only(['name', 'email', 'password', 'password_confirmation']);
    }
}
