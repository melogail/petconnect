<?php

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A token login. `device_name` names the token so the account's token list
 * reads as a list of installs; `code` / `recovery_code` are the second factor
 * for an account with 2FA enabled (see Actions\Auth\VerifyTwoFactorCode).
 */
class LoginRequest extends FormRequest
{
    /**
     * @return array<string, list<ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:10'],
            'recovery_code' => ['nullable', 'string', 'max:64'],
        ];
    }

    public function email(): string
    {
        return (string) $this->validated('email');
    }

    public function password(): string
    {
        return (string) $this->validated('password');
    }

    public function deviceName(): string
    {
        return (string) $this->validated('device_name');
    }

    public function code(): ?string
    {
        $code = $this->validated('code');

        return $code === null ? null : (string) $code;
    }

    public function recoveryCode(): ?string
    {
        $code = $this->validated('recovery_code');

        return $code === null ? null : (string) $code;
    }
}
