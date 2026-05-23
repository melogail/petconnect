<?php

namespace App\Http\Requests;

use App\Models\Comment;
use App\Models\Report;
use App\Models\Review;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;

class StoreReportRequest extends FormRequest
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
        return [
            'reportable_type' => ['required', 'string'],
            'reportable_id' => ['required', 'integer'],
            'reason' => ['required', 'string'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if (! auth()->check()) {
                return;
            }

            $reportableType = $this->input('reportable_type');
            $reportableId = (int) $this->input('reportable_id');

            if (! in_array($reportableType, [Review::class, Comment::class], true)) {
                return;
            }

            /** @var Review|Comment|null $reportable */
            $reportable = $reportableType::query()->find($reportableId);

            if ($reportable instanceof Model && $this->isOwnedByCurrentUser($reportable)) {
                $validator->errors()->add(
                    'reportable_id',
                    'You cannot report your own content.',
                );

                return;
            }

            $alreadyReported = Report::query()
                ->where('user_id', auth()->id())
                ->where('reportable_type', $reportableType)
                ->where('reportable_id', $reportableId)
                ->exists();

            if ($alreadyReported) {
                $validator->errors()->add(
                    'reportable_id',
                    'You have already reported this content.',
                );
            }
        });
    }

    protected function isOwnedByCurrentUser(Model $reportable): bool
    {
        return isset($reportable->user_id) && $reportable->user_id === auth()->id();
    }
}
