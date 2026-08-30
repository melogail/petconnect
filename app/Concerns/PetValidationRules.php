<?php

namespace App\Concerns;

use App\Enums\HealthStatus;
use App\Enums\ListingType;
use App\Enums\PetGender;
use App\Enums\PetStatus;
use App\Models\Breed;
use App\Models\Category;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * The pet listing form — its validation rules and its uploaded files — which
 * the store and update requests share.
 *
 * The edit form posts the whole listing, so both requests validate the same
 * fields and differ only in whether a cover photo has to be present.
 *
 * Every string rule backed by a varchar(255) column carries `max:255`, and the
 * three text columns (`description`, `detailed_address`, `special_needs`) carry
 * an explicit ceiling of their own. The legacy requests left the location and
 * description fields unbounded, so a 100 KB city name reached the database and
 * was truncated or rejected there.
 *
 * The nested `location.*`, `health.*` and `additionalInfo` keys are camelCase
 * and PetDetailResource emits them under exactly these names. They have to stay
 * in step: a key the resource emits that no rule here accepts is dropped by
 * validated() and then written as null by the update flow, which silently wipes
 * the field on save.
 */
trait PetValidationRules
{
    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function petRules(bool $requiresFeaturedImage): array
    {
        return [
            ...$this->basicRules(),
            ...$this->locationRules(),
            ...$this->imageRules($requiresFeaturedImage),
            ...$this->healthRules(),
            ...$this->traitRules(),
        ];
    }

    /**
     * Identity, taxonomy and listing terms.
     *
     * `breed_id` is only accepted when it belongs to the submitted category, so
     * a hand-edited payload cannot file a Siamese under Dogs; the pipeline
     * re-checks the pair and aborts if the row disappeared in between.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function basicRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'integer', Rule::exists(Category::class, 'id')],
            'breed_id' => [
                'nullable',
                'integer',
                Rule::exists(Breed::class, 'id')->where('category_id', $this->input('category_id')),
            ],
            'age' => ['required', 'numeric', 'min:0', 'max:99'],
            'gender' => ['required', Rule::enum(PetGender::class)],
            'color' => ['required', 'string', 'max:255'],
            'weight' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'description' => ['required', 'string', 'max:5000'],
            'listing_type' => ['required', Rule::enum(ListingType::class)],
            'price' => [
                'required_if:listing_type,'.ListingType::Sale->value,
                'nullable',
                'numeric',
                'min:0',
                'max:99999999.99',
            ],
            'status' => ['required', Rule::enum(PetStatus::class)],
        ];
    }

    /**
     * The nested location group the pipeline flattens onto flat columns.
     *
     * A coordinate pair is all or nothing: half a pair would place the listing
     * nowhere and silently drop it out of every nearby search.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function locationRules(): array
    {
        return [
            'location' => ['required', 'array'],
            'location.address' => ['nullable', 'string', 'max:255'],
            'location.detailedAddress' => ['nullable', 'string', 'max:1000'],
            'location.city' => ['required', 'string', 'max:255'],
            'location.state' => ['required', 'string', 'max:255'],
            'location.postalCode' => ['nullable', 'string', 'max:255'],
            'location.country' => ['required', 'string', 'max:255'],
            'location.coordinates' => ['nullable', 'array'],
            'location.coordinates.lat' => [
                'nullable',
                'required_with:location.coordinates.lng',
                'numeric',
                'between:-90,90',
            ],
            'location.coordinates.lng' => [
                'nullable',
                'required_with:location.coordinates.lat',
                'numeric',
                'between:-180,180',
            ],
        ];
    }

    /**
     * The cover photo and the gallery.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function imageRules(bool $requiresFeaturedImage): array
    {
        $image = ['image', 'mimes:jpeg,jpg,png,gif,webp', 'max:'.$this->maxImageKilobytes()];

        return [
            'featuredImage' => [$requiresFeaturedImage ? 'required' : 'nullable', ...$image],
            'images' => ['nullable', 'array', 'max:'.$this->maxGalleryImages()],
            'images.*' => ['required', ...$image],
        ];
    }

    /**
     * The nested health group, including its three repeaters.
     *
     * Repeater rows with a blank name are dropped by the pipeline rather than
     * rejected here, so a half-typed row does not block the whole form.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function healthRules(): array
    {
        return [
            'health' => ['nullable', 'array'],
            'health.status' => ['nullable', Rule::enum(HealthStatus::class)],
            'health.vaccinated' => ['nullable', 'boolean'],
            'health.spayedNeutered' => ['nullable', 'boolean'],
            'health.specialNeeds' => ['nullable', 'string', 'max:1000'],
            'health.lastVetVisit' => ['nullable', 'date', 'before_or_equal:today'],

            'health.vaccinations' => ['nullable', 'array', 'max:50'],
            'health.vaccinations.*' => ['array'],
            'health.vaccinations.*.name' => ['nullable', 'string', 'max:255'],
            'health.vaccinations.*.date' => ['nullable', 'date', 'before_or_equal:today'],

            'health.medications' => ['nullable', 'array', 'max:50'],
            'health.medications.*' => ['array'],
            'health.medications.*.name' => ['nullable', 'string', 'max:255'],
            'health.medications.*.usage' => ['nullable', 'string', 'max:255'],

            'health.allergies' => ['nullable', 'array', 'max:50'],
            'health.allergies.*' => ['nullable', 'string', 'max:255'],

            'health.vetName' => ['nullable', 'string', 'max:255'],
            'health.vetPhone' => ['nullable', 'string', 'max:20'],
        ];
    }

    /**
     * Personality traits and the free-form extras map.
     *
     * `additionalInfo` is a key/value map, not the legacy [{key, value}]
     * repeater; pairs missing either half are dropped by the pipeline.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function traitRules(): array
    {
        return [
            'traits' => ['nullable', 'array', 'max:20'],
            'traits.*' => ['nullable', 'string', 'max:255'],
            'additionalInfo' => ['nullable', 'array', 'max:20', $this->boundedKeys(255)],
            'additionalInfo.*' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Bound the *keys* of a free-form map, which `max` on `field.*` does not.
     *
     * `additional_info` is a user-authored key/value map that goes straight
     * into a JSON column, so an unbounded key is an unbounded write. Laravel
     * has no rule that reaches array keys, hence the closure.
     */
    protected function boundedKeys(int $maxLength): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($maxLength): void {
            if (! is_array($value)) {
                return;
            }

            foreach (array_keys($value) as $key) {
                if (mb_strlen((string) $key) > $maxLength) {
                    $fail(__('Each label must not be greater than :max characters.', [
                        'max' => $maxLength,
                    ]));

                    return;
                }
            }
        };
    }

    /**
     * The cover photo, when one was uploaded.
     */
    public function featuredImage(): ?UploadedFile
    {
        $image = $this->file('featuredImage');

        return $image instanceof UploadedFile ? $image : null;
    }

    /**
     * The newly uploaded gallery photos.
     *
     * @return list<UploadedFile>
     */
    public function galleryImages(): array
    {
        $images = $this->file('images', []);

        return array_values(array_filter(
            is_array($images) ? $images : [$images],
            fn (mixed $image): bool => $image instanceof UploadedFile,
        ));
    }

    /**
     * Per-image upload ceiling in kilobytes.
     */
    protected function maxImageKilobytes(): int
    {
        return (int) config('petconnect.pets.max_image_kilobytes', 512);
    }

    /**
     * How many gallery photos a listing may carry, excluding the cover photo.
     */
    protected function maxGalleryImages(): int
    {
        return (int) config('petconnect.pets.max_gallery_images', 3);
    }

    /**
     * @return array<string, string>
     */
    protected function petMessages(): array
    {
        return [
            'images.max' => __('You can upload a maximum of :count additional images.', [
                'count' => $this->maxGalleryImages(),
            ]),
            'images.*.max' => __('Each image must not exceed :size KB.', [
                'size' => $this->maxImageKilobytes(),
            ]),
            'featuredImage.max' => __('The featured image must not exceed :size KB.', [
                'size' => $this->maxImageKilobytes(),
            ]),
            'breed_id.exists' => __('The selected breed is not available for that category.'),
        ];
    }
}
