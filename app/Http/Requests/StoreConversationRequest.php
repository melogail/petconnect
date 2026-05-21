<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'other_user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id'),
                Rule::notIn([$this->user()->id]),
            ],
            'initial_message' => ['nullable', 'string', 'max:10000'],
        ];
    }
}
