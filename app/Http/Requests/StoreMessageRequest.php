<?php

namespace App\Http\Requests;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $conversation = $this->route('conversation');

        return $conversation instanceof Conversation
            && $this->user()?->can('view', $conversation) === true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'max:10000'],
            'type' => ['sometimes', 'string', Rule::in([Message::TYPE_TEXT])],
        ];
    }
}
