<?php

use App\Enums\PetStatus;
use App\Models\Breed;
use App\Models\Category;
use App\Models\Pet;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * The keys a listing with nothing to say about itself simply does not send.
 *
 * Every one of them is a collection, and Inertia's multipart serialiser appends
 * nothing at all for an empty array or an empty object, so a create with no
 * traits, no repeater rows, no extras and no map pin arrives with these six
 * keys missing altogether.
 *
 * @var list<string>
 */
const OPTIONAL_COLLECTION_KEYS = [
    'traits',
    'additionalInfo',
    'health.vaccinations',
    'health.medications',
    'health.allergies',
    'location.coordinates',
];

/**
 * A complete, valid pet form payload. Overrides are merged with array_replace_recursive
 * so a test can change one nested leaf without restating the group.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function petFormPayload(Category $category, array $overrides = []): array
{
    return array_replace_recursive([
        'name' => 'Luna',
        'category_id' => $category->getKey(),
        'breed_id' => null,
        'age' => '2',
        'gender' => 'female',
        'color' => 'Black',
        'weight' => '4.2',
        'description' => 'A calm indoor cat looking for a quiet home.',
        'listing_type' => 'adoption',
        'price' => null,
        'status' => 'available',
        'location' => [
            'address' => '12 Nile Street',
            'detailedAddress' => 'Building 3, Apartment 7',
            'city' => 'Cairo',
            'state' => 'Cairo',
            'postalCode' => '11511',
            'country' => 'Egypt',
            'coordinates' => ['lat' => '30.0444', 'lng' => '31.2357'],
        ],
        'health' => [
            'status' => 'healthy',
            'vaccinated' => true,
            'spayedNeutered' => true,
            'specialNeeds' => 'None',
            'lastVetVisit' => '2024-01-15',
            'vaccinations' => [['name' => 'Rabies', 'date' => '2024-01-15']],
            'medications' => [['name' => 'Flea drops', 'usage' => 'Monthly']],
            'allergies' => ['Dust'],
            'vetName' => 'Dr. Hana',
            'vetPhone' => '+20-100-000-0000',
        ],
        'traits' => ['friendly'],
        'additionalInfo' => ['house_trained' => 'yes'],
    ], $overrides);
}

/**
 * Attach a gallery photo to a listing, the way the pipeline does.
 */
function attachGalleryPhoto(Pet $pet, string $name = 'gallery.jpg'): Media
{
    return $pet->addMedia(UploadedFile::fake()->image($name))
        ->toMediaCollection(Pet::PHOTO_COLLECTION);
}

describe('show', function () {
    test('a guest can read a listing', function () {
        $pet = Pet::factory()->create();

        $this->get(route('pets.show', $pet))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('pets/Show')
                ->where('pet.id', $pet->getKey()));
    });

    test('a visit counts a view on the listing', function () {
        $pet = Pet::factory()->create(['views' => 4]);

        $this->get(route('pets.show', $pet))->assertOk();

        expect($pet->fresh()->views)->toBe(5);
    });

    test('returns 404 for a soft deleted listing', function () {
        $pet = Pet::factory()->create();
        $pet->delete();

        $this->get(route('pets.show', $pet))->assertNotFound();
    });

    /**
     * This page hosts the comment composer, which cannot draw a counter for a
     * ceiling it has not been told, and the thread, which cannot work out which
     * page to ask for next without the endpoint's page size. Both bounds are
     * read through the CommentValidationRules accessors the `max:` rule and
     * ListCommentThread's default `perPage` are built from, so moving either
     * config moves both ends of it — which is what the non-default values here
     * check and a hardcoded prop would fail.
     *
     * Both are asserted against a re-`config()`d value rather than the default,
     * because a prop frozen at today's default agrees with the drift it exists
     * to catch.
     */
    test('ships the comment bounds the composer and the thread are drawn from', function () {
        config([
            'petconnect.comments.max_length' => 140,
            'petconnect.comments.thread_per_page' => 3,
        ]);
        $pet = Pet::factory()->create();

        $this->get(route('pets.show', $pet))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('commentBounds', ['max_length' => 140, 'thread_per_page' => 3]));

        $this->actingAs(User::factory()->create())
            ->from(route('pets.show', $pet))
            ->post(route('comments.store', ['commentable_type' => 'pet', 'commentable_id' => $pet->getKey()]), [
                'content' => str_repeat('a', 141),
            ])
            ->assertInvalid(['content']);
    });
});

describe('create', function () {
    test('redirects a guest to the login page', function () {
        $this->get(route('pets.create'))->assertRedirect(route('login'));
    });

    test('redirects an unverified user to the verification notice', function () {
        $this->actingAs(User::factory()->unverified()->create())
            ->get(route('pets.create'))
            ->assertRedirect(route('verification.notice'));
    });

    test('renders the form for a verified user', function () {
        $this->actingAs(User::factory()->create())
            ->get(route('pets.create'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('pets/Create'));
    });

    /**
     * The photo step caps the gallery and compresses each file to fit the
     * per-image ceiling before it is attached, and both numbers were hardcoded
     * in `pets/Create.vue` against `config/petconnect.php`. The prop is read
     * through the same PetPhotoRules accessors the `max:` rules are built from,
     * so moving the config moves both — which is the whole point, and which
     * only a **non-default** value can show: asserting 3 and 512 here would
     * pass against a prop that was still hardcoded.
     *
     * The upload below is the other half of the pair. `->image()` writes real
     * bytes (never `->create()`, per .ai/rules/tests.md) and `->size(300)` is
     * what the `max:` rule reads, so a 300 KB cover photo is under the 512 KB
     * default and over the 256 KB this test configures. If the rule stopped
     * reading the accessor the prop is built from, this half fails while the
     * prop assertion above still passes.
     */
    test('ships the photo ceilings the picker enforces, and enforces the same ones', function () {
        config([
            'petconnect.pets.max_gallery_images' => 5,
            'petconnect.pets.max_image_kilobytes' => 256,
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('pets.create'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('photoBounds', ['max_gallery_images' => 5, 'max_image_kilobytes' => 256]));

        $category = Category::factory()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('pets.store'), petFormPayload($category, [
                'featuredImage' => UploadedFile::fake()->image('cover.jpg')->size(300),
            ]))
            ->assertInvalid(['featuredImage' => 'The featured image must not exceed 256 KB.']);

        expect(Pet::query()->count())->toBe(0);
    });
});

describe('store', function () {
    test('redirects a guest to the login page and writes nothing', function () {
        $category = Category::factory()->create();

        $this->post(route('pets.store'), petFormPayload($category))
            ->assertRedirect(route('login'));

        expect(Pet::query()->count())->toBe(0);
    });

    test('redirects an unverified user to the verification notice and writes nothing', function () {
        $category = Category::factory()->create();

        $this->actingAs(User::factory()->unverified()->create())
            ->post(route('pets.store'), petFormPayload($category))
            ->assertRedirect(route('verification.notice'));

        expect(Pet::query()->count())->toBe(0);
    });

    test('publishes a listing owned by the acting user', function () {
        Storage::fake(config('media-library.disk_name'));
        $owner = User::factory()->create();
        $category = Category::factory()->create();

        $response = $this->actingAs($owner)->post(route('pets.store'), petFormPayload($category, [
            'featuredImage' => UploadedFile::fake()->image('cover.jpg'),
        ]));

        $pet = Pet::query()->sole();

        $response->assertRedirect(route('pets.show', $pet));
        $this->assertDatabaseHas('pets', [
            'id' => $pet->getKey(),
            'user_id' => $owner->getKey(),
            'category_id' => $category->getKey(),
            'name' => 'Luna',
            'city' => 'Cairo',
            'vet_phone' => '+20-100-000-0000',
        ]);
        expect($pet->featuredPhoto())->not->toBeNull();
    });

    test('publishes a listing that carries no traits, no repeaters and no map pin', function () {
        Storage::fake(config('media-library.disk_name'));
        $owner = User::factory()->create();
        $category = Category::factory()->create();

        $payload = petFormPayload($category, [
            'featuredImage' => UploadedFile::fake()->image('cover.jpg'),
        ]);

        foreach (OPTIONAL_COLLECTION_KEYS as $key) {
            Arr::forget($payload, $key);
        }

        $response = $this->actingAs($owner)->post(route('pets.store'), $payload)->assertValid();

        $pet = Pet::query()->sole();

        $response->assertRedirect(route('pets.show', $pet));
        $this->assertDatabaseHas('pets', [
            'id' => $pet->getKey(),
            'user_id' => $owner->getKey(),
            'name' => 'Luna',
            'traits' => null,
            'additional_info' => null,
            'vaccinations' => null,
            'medications' => null,
            'allergies' => null,
            'latitude' => null,
            'longitude' => null,
        ]);
    });

    test('rejects a payload that omits a key the write bag owns, and publishes nothing', function (string $omitted) {
        $owner = User::factory()->create();
        $category = Category::factory()->create();

        $payload = petFormPayload($category, [
            'featuredImage' => UploadedFile::fake()->image('cover.jpg'),
        ]);
        Arr::forget($payload, $omitted);

        $this->actingAs($owner)
            ->post(route('pets.store'), $payload)
            ->assertInvalid([$omitted => 'must be present']);

        expect(Pet::query()->count())->toBe(0);
    })->with([
        "the veterinarian's phone number" => ['health.vetPhone'],
        'the whole health group' => ['health'],
    ]);

    test('rejects a breed that belongs to another category', function () {
        $owner = User::factory()->create();
        $category = Category::factory()->create();
        $otherBreed = Breed::factory()->for(Category::factory())->create();

        $this->actingAs($owner)
            ->post(route('pets.store'), petFormPayload($category, ['breed_id' => $otherBreed->getKey()]))
            ->assertInvalid(['breed_id' => 'The selected breed is not available for that category.']);

        expect(Pet::query()->count())->toBe(0);
    });

    test('rejects a value longer than the column it is written to', function (string $field, int $limit) {
        $owner = User::factory()->create();
        $category = Category::factory()->create();

        $payload = petFormPayload($category);
        data_set($payload, $field, str_repeat('a', $limit + 1));

        $this->actingAs($owner)
            ->post(route('pets.store'), $payload)
            ->assertInvalid([$field => 'must not be greater than '.$limit]);

        expect(Pet::query()->count())->toBe(0);
    })->with([
        'name' => ['name', 255],
        'street address' => ['location.address', 255],
        'city' => ['location.city', 255],
        'postal code' => ['location.postalCode', 255],
        "veterinarian's phone number" => ['health.vetPhone', 20],
    ]);
});

describe('edit', function () {
    test('returns 403 for a user who does not own the listing', function () {
        $pet = Pet::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('pets.edit', $pet))
            ->assertForbidden();
    });

    test('renders the form for the owner', function () {
        $owner = User::factory()->create();
        $pet = Pet::factory()->for($owner)->create();

        $this->actingAs($owner)
            ->get(route('pets.edit', $pet))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('pets/Edit')
                ->where('pet.id', $pet->getKey()));
    });

    /**
     * `pets/Edit.vue` carried the same two hardcoded numbers `pets/Create.vue`
     * did, so the edit form needs the prop as much as the create form. Only the
     * prop is asserted here: `UpdatePetRequest` builds its `max:` rules from
     * the same PetPhotoRules accessors `StorePetRequest` does, and the create
     * test already proves the rule and the prop move together.
     */
    test('ships the same photo ceilings to the edit form', function () {
        config([
            'petconnect.pets.max_gallery_images' => 5,
            'petconnect.pets.max_image_kilobytes' => 256,
        ]);
        $owner = User::factory()->create();
        $pet = Pet::factory()->for($owner)->create();

        $this->actingAs($owner)
            ->get(route('pets.edit', $pet))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('photoBounds', ['max_gallery_images' => 5, 'max_image_kilobytes' => 256]));
    });
});

describe('update', function () {
    test('returns 403 for a user who does not own the listing and leaves it unchanged', function () {
        $category = Category::factory()->create();
        $pet = Pet::factory()->for($category)->create(['name' => 'Original']);

        $this->actingAs(User::factory()->create())
            ->put(route('pets.update', $pet), petFormPayload($category))
            ->assertForbidden();

        expect($pet->fresh()->name)->toBe('Original');
    });

    test('applies the edit for the owner', function () {
        $owner = User::factory()->create();
        $category = Category::factory()->create();
        $pet = Pet::factory()->for($owner)->for($category)->create(['name' => 'Original']);

        $this->actingAs($owner)
            ->put(route('pets.update', $pet), petFormPayload($category, ['name' => 'Renamed']))
            ->assertRedirect(route('pets.show', $pet));

        $this->assertDatabaseHas('pets', [
            'id' => $pet->getKey(),
            'name' => 'Renamed',
            'user_id' => $owner->getKey(),
        ]);
    });

    test('returns 422 for a payload that omits a key the write bag owns, and leaves the listing unchanged', function (string $omitted) {
        $owner = User::factory()->create();
        $category = Category::factory()->create();
        $pet = Pet::factory()->for($owner)->for($category)->create([
            'vet_phone' => '+20-100-000-0000',
            'special_needs' => 'Needs daily insulin',
        ]);

        $payload = petFormPayload($category, ['name' => 'Renamed']);
        Arr::forget($payload, $omitted);

        $this->actingAs($owner)
            ->putJson(route('pets.update', $pet), $payload)
            ->assertUnprocessable()
            ->assertInvalid([$omitted => 'must be present']);

        $this->assertDatabaseHas('pets', [
            'id' => $pet->getKey(),
            'name' => $pet->name,
            'vet_phone' => '+20-100-000-0000',
            'special_needs' => 'Needs daily insulin',
        ]);
    })->with([
        "the veterinarian's phone number" => ['health.vetPhone'],
        'the whole health group' => ['health'],
    ]);

    test('clears a field sent as null, because a PUT replaces the listing', function () {
        $owner = User::factory()->create();
        $category = Category::factory()->create();
        $pet = Pet::factory()->for($owner)->for($category)->create([
            'vet_name' => 'Dr. Hana',
            'vet_phone' => '+20-100-000-0000',
            'special_needs' => 'Needs daily insulin',
        ]);

        $this->actingAs($owner)
            ->put(route('pets.update', $pet), petFormPayload($category, [
                'health' => ['vetName' => null, 'vetPhone' => null, 'specialNeeds' => null],
            ]))
            ->assertRedirect(route('pets.show', $pet));

        $this->assertDatabaseHas('pets', [
            'id' => $pet->getKey(),
            'vet_name' => null,
            'vet_phone' => null,
            'special_needs' => null,
        ]);
    });

    test('ignores a media id that belongs to another listing', function () {
        Storage::fake(config('media-library.disk_name'));
        $owner = User::factory()->create();
        $category = Category::factory()->create();
        $pet = Pet::factory()->for($owner)->for($category)->create();
        $ownPhoto = attachGalleryPhoto($pet);
        $otherPhoto = attachGalleryPhoto(Pet::factory()->create(), 'stranger.jpg');

        $this->actingAs($owner)
            ->put(route('pets.update', $pet), petFormPayload($category, [
                'deletedMediaIds' => [$otherPhoto->getKey()],
            ]))
            ->assertRedirect(route('pets.show', $pet));

        $this->assertModelExists($otherPhoto);
        $this->assertModelExists($ownPhoto);
    });

    test('removes a media id that belongs to the listing', function () {
        Storage::fake(config('media-library.disk_name'));
        $owner = User::factory()->create();
        $category = Category::factory()->create();
        $pet = Pet::factory()->for($owner)->for($category)->create();
        $photo = attachGalleryPhoto($pet);

        $this->actingAs($owner)
            ->put(route('pets.update', $pet), petFormPayload($category, [
                'deletedMediaIds' => [$photo->getKey()],
            ]))
            ->assertRedirect(route('pets.show', $pet));

        $this->assertModelMissing($photo);
    });

    test('rejects an edit that would push the gallery past its cap, before anything is written', function () {
        Storage::fake(config('media-library.disk_name'));
        config(['petconnect.pets.max_gallery_images' => 3]);
        $owner = User::factory()->create();
        $category = Category::factory()->create();
        $pet = Pet::factory()->for($owner)->for($category)->create(['name' => 'Original']);

        foreach (['a.jpg', 'b.jpg', 'c.jpg'] as $name) {
            attachGalleryPhoto($pet, $name);
        }

        $this->actingAs($owner)
            ->put(route('pets.update', $pet), petFormPayload($category, [
                'name' => 'Renamed',
                'images' => [UploadedFile::fake()->image('d.jpg')],
            ]))
            ->assertInvalid(['images' => 'This listing can hold 3 additional images; the edit would leave it with 4.']);

        expect($pet->fresh()->name)->toBe('Original')
            ->and($pet->fresh()->galleryPhotos())->toHaveCount(3);
    });
});

describe('destroy', function () {
    test('returns 403 for a user who does not own the listing and leaves it in place', function () {
        $pet = Pet::factory()->create();

        $this->actingAs(User::factory()->create())
            ->delete(route('pets.destroy', $pet))
            ->assertForbidden();

        $this->assertNotSoftDeleted($pet);
    });

    test('soft deletes the listing for the owner', function () {
        $owner = User::factory()->create();
        $pet = Pet::factory()->for($owner)->create();

        $this->actingAs($owner)
            ->delete(route('pets.destroy', $pet))
            ->assertRedirect(route('home'));

        $this->assertSoftDeleted($pet);
    });
});

describe('status toggle', function () {
    test('returns 403 for a user who does not own the listing and leaves the status alone', function () {
        $pet = Pet::factory()->available()->create();

        $this->actingAs(User::factory()->create())
            ->patch(route('pets.status.toggle', $pet))
            ->assertForbidden();

        expect($pet->fresh()->status)->toBe(PetStatus::Available);
    });

    test('flips the listing to unavailable for the owner', function () {
        $owner = User::factory()->create();
        $pet = Pet::factory()->for($owner)->available()->create();

        $this->actingAs($owner)
            ->from(route('pets.show', $pet))
            ->patch(route('pets.status.toggle', $pet))
            ->assertRedirect(route('pets.show', $pet));

        expect($pet->fresh()->status)->toBe(PetStatus::Unavailable);
    });
});

describe('like', function () {
    test('records a like for a verified user', function () {
        $liker = User::factory()->create();
        $pet = Pet::factory()->create();

        $this->actingAs($liker)
            ->from(route('pets.show', $pet))
            ->post(route('pets.like', $pet))
            ->assertRedirect(route('pets.show', $pet));

        $this->assertDatabaseHas('likes', [
            'user_id' => $liker->getKey(),
            'likeable_id' => $pet->getKey(),
        ]);
    });

    test('returns 429 once the acting user passes 30 likes in a minute', function () {
        $liker = User::factory()->create();
        $pet = Pet::factory()->create();

        for ($attempt = 0; $attempt < 30; $attempt++) {
            $this->actingAs($liker)->post(route('pets.like', $pet))->assertRedirect();
        }

        $this->actingAs($liker)->post(route('pets.like', $pet))->assertTooManyRequests();
    });
});
