<?php

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A token login through a provider: the mobile app runs the Google or
 * Facebook SDK itself and posts the access token it was handed, plus the
 * `device_name` every token login names its bearer token after.
 */
class SocialLoginRequest extends FormRequest
{
    /**
     * @return array<string, list<ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'token'       => ['required', 'string', 'max:4096'],
            'device_name' => ['required', 'string', 'max:255'],
        ];
    }

    public function token(): string
    {
        return (string) $this->validated('token');
    }

    public function deviceName(): string
    {
        return (string) $this->validated('device_name');
    }
}
