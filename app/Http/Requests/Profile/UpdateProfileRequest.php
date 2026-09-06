<?php

namespace App\Http\Requests\Profile;

use App\Concerns\ProfileValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * The settings profile form.
 *
 * Rules live in App\Concerns\ProfileValidationRules, which is also what
 * registration validates through, so the name and email rules cannot drift
 * between the two.
 *
 * No authorize(): .ai/rules/controllers.md puts authorization in the controller
 * with `$this->authorize()`, and Settings\ProfileController::update calls it
 * against UserPolicy. The legacy UpdateProfileRequest did
 * `return request()->user()->id == $this->user->id` in authorize(), which is
 * exactly the split this project stopped doing — the same decision lived in a
 * request, a controller and a policy depending on which endpoint you read.
 *
 * The accessors below are what keep the controller thin: it forwards
 * `profileAttributes()` and `uploadedImage()` to Actions\Profiles\UpdateProfile
 * without ever touching the request bag itself.
 *
 * `profileAttributes()` strips the one key that is not an attribute, so the
 * pipeline's persist step is handed only what belongs on the model. It strips
 * it here as well as there on purpose: this class is the one that knows it was
 * a form field.
 *
 * ## This form no longer changes a password
 *
 * It used to accept `current_password` and `password` alongside the avatar and
 * the address, duplicating Fortify's `user-password.update`, which
 * `settings/Security` already posts to. Both keys are gone from the rules and
 * both accessors are gone from here; see App\Concerns\ProfileValidationRules
 * for why the dedicated endpoint is the single path and what that diverges
 * from.
 */
class UpdateProfileRequest extends FormRequest
{
    use ProfileValidationRules;

    /**
     * Keys the form carries that are not user attributes.
     *
     * @var list<string>
     */
    protected const NON_ATTRIBUTE_KEYS = ['image'];

    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        return $this->profileFormRules($this->user()->id);
    }

    /**
     * The validated fields that belong on the user row.
     *
     * @return array<string, mixed>
     */
    public function profileAttributes(): array
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return array_diff_key($validated, array_flip(self::NON_ATTRIBUTE_KEYS));
    }

    /**
     * The uploaded avatar, or null.
     *
     * Not `image()`: Illuminate\Http\Request declares
     * `image(string $key): ?Illuminate\Image\Image` in Laravel 13, so a method
     * of that name on a FormRequest is a fatal signature clash at class-load
     * time — the whole application, not just this endpoint.
     */
    public function uploadedImage(): ?UploadedFile
    {
        $file = $this->file('image');

        return $file instanceof UploadedFile ? $file : null;
    }
}
