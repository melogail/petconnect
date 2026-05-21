<?php

namespace App\Http\Requests;

use App\Enums\Commentable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCommentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Commentable $type */
        $type = $this->route('commentable_type');
        $id = (int) $this->route('commentable_id');

        return [
            'content' => ['required', 'string', 'max:500'],
            'parent_id' => [
                'nullable',
                Rule::exists('comments', 'id')->where(fn ($query) => $query
                    ->where('commentable_type', $type->modelClass())
                    ->where('commentable_id', $id)),
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'parent_id.exists' => 'The parent comment does not belong to this resource.',
        ];
    }
}
