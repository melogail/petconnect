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
 * **Every scalar key the write bag owns carries `present`.** A PUT on a pet is
 * a full replacement, not a patch: the Normalize* steps build a value for every
 * column the form owns, so a key the request omits is written as null.
 * `present` allows null and rejects absence, which turns "the form forgot to
 * send health.vetPhone" into a 422 instead of a silent wipe of the vet's
 * details, the special needs note and the coordinates that keep the listing in
 * nearby search. Clearing a field is still one payload away — send it as null.
 *
 * `present` is on the store rules too, so there is one contract rather than two
 * and a create form cannot drift from an edit form built out of the same
 * component. It is deliberately NOT on `featuredImage`, `images` or
 * `deletedMediaIds`: those are handled by the media steps and never reach the
 * attribute bag, so omitting them changes nothing.
 *
 * **`present` is never placed on a collection key, because a multipart request
 * cannot express one.** `store` always carries a required `featuredImage` file,
 * so a create is always sent as multipart/form-data, and Inertia's FormData
 * serialiser (`@inertiajs/core`, `objectToFormData`) appends *nothing at all*
 * for an empty array or an empty object while a null becomes `''` and survives
 * as null through ConvertEmptyStringsToNull. A listing with no traits, no
 * vaccinations, no medications, no allergies, no extras and no map pin
 * therefore arrives with those six keys simply missing, and `present` on them
 * would 422 every such create. For a collection, absent and empty mean the same
 * thing anyway, so `present` bought nothing there; for a scalar, absent versus
 * null is exactly the silent-wipe distinction being guarded.
 *
 * The one rule that decides the whole set: **a key carries `present` when it is
 * a scalar, or when it is a group whose own leaves all carry `present` (and are
 * therefore always serialised, which keeps the group serialised too).** That is
 * why `health` is `present` while `location.coordinates` is not — see
 * healthRules() and locationRules(). Note that PHP's test client preserves
 * arrays whatever the declared transport, so no feature test can see this;
 * .ai/rules/requests.md and .ai/rules/js.md carry the constraint instead.
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
                'present',
                'nullable',
                'integer',
                Rule::exists(Breed::class, 'id')->where('category_id', $this->input('category_id')),
            ],
            'age' => ['required', 'numeric', 'min:0', 'max:99'],
            'gender' => ['required', Rule::enum(PetGender::class)],
            'color' => ['required', 'string', 'max:255'],
            'weight' => ['present', 'nullable', 'numeric', 'min:0', 'max:999999.99'],
            'description' => ['required', 'string', 'max:5000'],
            'listing_type' => ['required', Rule::enum(ListingType::class)],
            'price' => [
                'present',
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
     * nowhere and silently drop it out of every nearby search. `required_with`
     * on each leaf is what forbids half a pair; the leaves stay absence-tolerant
     * because the group may legitimately be null or empty.
     *
     * `coordinates` itself is not `present`. An unpinned listing sends
     * `coordinates: {}`, which a multipart create serialises to nothing at all,
     * so `present` there would 422 every create without a map pin. It is the
     * mirror image of `health`, whose leaves are all `present` and therefore
     * always serialised — see the trait docblock for the single rule both follow.
     * Residual risk this leaves open: renaming *both* leaves at once (say to
     * `latitude`/`longitude`) passes validation and silently unpins the listing,
     * because `required_with` only fires when one of the pair survives. The
     * resource↔Form-Request parity test is what catches that.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function locationRules(): array
    {
        return [
            'location' => ['required', 'array'],
            'location.address' => ['present', 'nullable', 'string', 'max:255'],
            'location.detailedAddress' => ['present', 'nullable', 'string', 'max:1000'],
            'location.city' => ['required', 'string', 'max:255'],
            'location.state' => ['required', 'string', 'max:255'],
            'location.postalCode' => ['present', 'nullable', 'string', 'max:255'],
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
     * No `present` here: the media steps own these, they never reach the
     * attribute bag, and an omitted `images` leaves the existing gallery alone.
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
     * `health` is `present` and an array rather than nullable: every leaf under
     * it is `present` too, so a payload sending `health: null` could never
     * satisfy both. Clearing the group means sending its keys as null, which is
     * what the form does when the user empties the fields. That also makes
     * `present` expressible over multipart — twelve always-serialised scalar
     * leaves mean `health` is never an empty object on the wire — which is why
     * it keeps `present` where `location.coordinates` and the three repeaters
     * below do not.
     *
     * The repeaters are collections, so they drop `present`: an empty one is
     * indistinguishable from an absent one, and a multipart create sends
     * nothing for it. Their row leaves keep `present`, which costs nothing —
     * wildcard rules generate no attributes at all when the collection is empty.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function healthRules(): array
    {
        return [
            'health' => ['present', 'array'],
            'health.status' => ['present', 'nullable', Rule::enum(HealthStatus::class)],
            'health.vaccinated' => ['present', 'nullable', 'boolean'],
            'health.spayedNeutered' => ['present', 'nullable', 'boolean'],
            'health.specialNeeds' => ['present', 'nullable', 'string', 'max:1000'],
            'health.lastVetVisit' => ['present', 'nullable', 'date', 'before_or_equal:today'],

            'health.vaccinations' => ['nullable', 'array', 'max:50'],
            'health.vaccinations.*' => ['array'],
            'health.vaccinations.*.name' => ['present', 'nullable', 'string', 'max:255'],
            'health.vaccinations.*.date' => ['present', 'nullable', 'date', 'before_or_equal:today'],

            'health.medications' => ['nullable', 'array', 'max:50'],
            'health.medications.*' => ['array'],
            'health.medications.*.name' => ['present', 'nullable', 'string', 'max:255'],
            'health.medications.*.usage' => ['present', 'nullable', 'string', 'max:255'],

            'health.allergies' => ['nullable', 'array', 'max:50'],
            'health.allergies.*' => ['nullable', 'string', 'max:255'],

            'health.vetName' => ['present', 'nullable', 'string', 'max:255'],
            'health.vetPhone' => ['present', 'nullable', 'string', 'max:20'],
        ];
    }

    /**
     * Personality traits and the free-form extras map.
     *
     * `additionalInfo` is a key/value map, not the legacy [{key, value}]
     * repeater; pairs missing either half are dropped by the pipeline.
     *
     * Both keys are collections, so neither is `present`: a listing with no
     * traits and no extras sends `[]` / `{}`, which a multipart create puts on
     * the wire as nothing at all.
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
