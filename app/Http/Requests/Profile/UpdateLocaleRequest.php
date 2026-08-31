<?php

namespace App\Http\Requests\Profile;

use App\Concerns\ProfileValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Switching the interface language.
 *
 * Guests post this too — the language picker is in the header of every page,
 * including the ones you can read without an account — so there is no
 * authorization here and none in the controller: `locale.update` governs no
 * model, and .ai/rules/controllers.md exempts exactly that case.
 *
 * The whitelist is `petconnect.locales.supported` through
 * App\Concerns\ProfileValidationRules::localeRules(), the same list the profile
 * form's `locale` field validates against and the same one
 * Actions\Profiles\ApplyUserLocale falls back from. One list, three readers.
 *
 * The rule is asked for in its `required` form, because unlike the profile form
 * this request exists only to change the language and an empty submission means
 * nothing.
 */
class UpdateLocaleRequest extends FormRequest
{
    use ProfileValidationRules;

    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        return [
            'locale' => $this->localeRules(required: true),
        ];
    }

    public function locale(): string
    {
        return (string) $this->validated('locale');
    }
}
