<?php

namespace App\Http\Resources\Pet;

use App\Models\Pet;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A listing as its own page renders it.
 *
 * The owner-only leaves — street address, building detail, exact coordinates,
 * medications, allergies and the veterinarian's name and phone number — are
 * emitted only for a viewer who can update the listing, and are simply absent
 * otherwise. The legacy edit page had no ownership check at all, so any
 * verified account could read all of that for any pet; gating it on the same
 * policy the edit route uses closes it from the payload side as well.
 *
 * ## The edit form contract
 *
 * Every key the edit form posts back is emitted here under exactly the name the
 * Form Request accepts, so prefilling those fields is a straight assignment.
 * The nested `location` and `health` groups use camelCase leaves —
 * `postalCode`, `detailedAddress`, `spayedNeutered`, `specialNeeds`,
 * `lastVetVisit`, `vetName`, `vetPhone` — and so does `additionalInfo`, because
 * that is what App\Concerns\PetValidationRules validates. They used to be
 * emitted in snake_case, which no rule matched: the value was dropped by
 * validated() and then written as null by the update flow, silently wiping the
 * vet's name, the postal code and the rest on every save.
 *
 * Four keys are deliberately *not* a straight assignment, because they are
 * read shapes rather than write shapes:
 * - `category` / `breed` are objects; the form posts `category_id`/`breed_id`.
 * - `featured_image` is a URL; the form posts `featuredImage`, an upload.
 * - `photos` are the attached media rows; the form posts `images` (new uploads)
 *   and `deletedMediaIds` (ids to detach). The read key is deliberately *not*
 *   called `images`: it used to be, and since a client is told above to send
 *   back every field it received, round-tripping it posted media objects into
 *   the `images` upload rule and 422'd. The write name stays `images` because it
 *   is the file input's name and is referenced by string in petMessages() and
 *   galleryImages().
 *
 * ## PUT is a full replacement
 *
 * The update flow writes a complete attribute bag, so a field the form omits is
 * written as null — a PUT replaces the listing rather than patching it. That is
 * the intended semantics: the edit form posts the whole listing, and a partial
 * write would make "I cleared the vet's phone number" indistinguishable from "I
 * did not send that field". A client must therefore send back every field it
 * received here, which is exactly what this shape is for.
 *
 * @mixin Pet
 */
class PetDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isOwner = $request->user()?->can('update', $this->resource) ?? false;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'age' => $this->age,
            'gender' => $this->gender,
            'color' => $this->color,
            'weight' => $this->weight,
            'description' => $this->description,
            'listing_type' => $this->listing_type,
            'price' => $this->price,
            'status' => $this->status,
            'views' => $this->views,

            'category' => PetCategoryOptionResource::make($this->whenLoaded('category')),
            'breed' => PetBreedOptionResource::make($this->whenLoaded('breed')),
            'user' => PetOwnerResource::make($this->whenLoaded('user')),
            'is_owner' => $request->user()?->getKey() === $this->user_id,

            'location' => [
                'city' => $this->city,
                'state' => $this->state,
                'country' => $this->country,
                'postalCode' => $this->postal_code,
                'address' => $this->when($isOwner, fn (): ?string => $this->address),
                'detailedAddress' => $this->when($isOwner, fn (): ?string => $this->detailed_address),
                'coordinates' => $this->when($isOwner, fn (): array => [
                    'lat' => $this->latitude,
                    'lng' => $this->longitude,
                ]),
            ],

            'health' => [
                'status' => $this->health_status,
                'vaccinated' => $this->vaccinated,
                'spayedNeutered' => $this->spayed_neutered,
                'specialNeeds' => $this->special_needs,
                'lastVetVisit' => $this->last_vet_visit?->toDateString(),
                'vaccinations' => $this->vaccinations,
                'medications' => $this->when($isOwner, fn (): ?array => $this->medications),
                'allergies' => $this->when($isOwner, fn (): ?array => $this->allergies),
                'vetName' => $this->when($isOwner, fn (): ?string => $this->vet_name),
                'vetPhone' => $this->when($isOwner, fn (): ?string => $this->vet_phone),
            ],

            'traits' => $this->traits,
            'additionalInfo' => $this->additional_info,

            'featured_image' => $this->featuredPhotoUrl('display'),
            'photos' => PetMediaResource::collection($this->whenLoaded('media')),

            'likes_count' => (int) ($this->likes_count ?? 0),
            'comments_count' => (int) ($this->comments_count ?? 0),
            'is_liked' => (bool) ($this->is_liked ?? false),
            'comments' => PetCommentResource::collection($this->whenLoaded('comments')),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
