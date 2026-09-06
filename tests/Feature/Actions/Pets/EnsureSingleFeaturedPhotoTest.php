<?php

use App\Actions\Pets\EnsureSingleFeaturedPhoto;
use App\Models\Admin;
use App\Models\Pet;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Attach photos to a listing in the given order, flagging the named ones as
 * cover candidates exactly as Nova's checkbox does — a `featured` custom
 * property on the media row, not a collection of its own.
 *
 * @param  list<string>  $names
 * @param  list<string>  $featured
 * @return Collection<string, Media>
 */
function attachPhotos(Pet $pet, array $names, array $featured): Collection
{
    return collect($names)->mapWithKeys(fn (string $name): array => [
        $name => $pet->addMedia(UploadedFile::fake()->image($name))
            ->withCustomProperties(in_array($name, $featured, true) ? [Pet::FEATURED_PROPERTY => true] : [])
            ->toMediaCollection(Pet::PHOTO_COLLECTION),
    ]);
}

/**
 * The whole update payload App\Nova\Pet requires, rebuilt from the listing's
 * own attributes so a test only has to state the part it is changing. Every
 * key here carries a `required` rule on the resource.
 *
 * @return array<string, mixed>
 */
function novaPetPayload(Pet $pet): array
{
    return [
        'name' => $pet->name,
        'user' => $pet->user_id,
        'category' => $pet->category_id,
        'breed' => $pet->breed_id,
        'status' => $pet->status->value,
        'listing_type' => $pet->listing_type->value,
        'gender' => $pet->gender->value,
        'age' => $pet->age,
        'color' => $pet->color,
        'city' => $pet->city,
        'state' => $pet->state,
        'country' => $pet->country,
        'health_status' => $pet->health_status->value,
        'description' => $pet->description,
    ];
}

beforeEach(function () {
    Storage::fake(config('media-library.disk_name'));
});

/**
 * The cover is the member of `pets` whose `featured` property is true, and
 * `Pet::featuredPhoto()` reads it with `->first()`. Nova's Images field puts the
 * checkbox on every photo, so an admin can tick three and save — and `->first()`
 * then picks by collection order, which is not the order the boxes were in and
 * is not stable against a reorder or a deletion. The same listing renders a
 * different cover for no visible reason.
 *
 * The one kept is the one that was already on screen: this settles the stored
 * data to agree with what the page rendered rather than choosing a new cover.
 */
test('demotes every flagged photo but the one the cover already rendered', function () {
    $pet = Pet::factory()->create();
    $photos = attachPhotos($pet, ['first.jpg', 'second.jpg', 'third.jpg'], ['first.jpg', 'second.jpg', 'third.jpg']);

    $renderedCover = $pet->featuredPhoto();

    $demoted = app(EnsureSingleFeaturedPhoto::class)->handle($pet);

    $stillFeatured = $pet->fresh()->getMedia(Pet::PHOTO_COLLECTION, [Pet::FEATURED_PROPERTY => true]);

    expect($demoted)->toBe(2)
        ->and($stillFeatured->map->getKey()->all())->toBe([$photos['first.jpg']->getKey()])
        ->and($renderedCover->getKey())->toBe($photos['first.jpg']->getKey());
});

/**
 * `Pet::galleryPhotos()` rejects *every* flagged photo, so three ticked boxes
 * did not merely make the cover arbitrary — they dropped two images out of the
 * gallery as well, leaving a detail page with a cover and nothing else.
 */
test('returns the over-flagged photos to the gallery', function () {
    $pet = Pet::factory()->create();
    attachPhotos($pet, ['first.jpg', 'second.jpg', 'third.jpg'], ['first.jpg', 'second.jpg', 'third.jpg']);

    expect($pet->galleryPhotos())->toHaveCount(0);

    app(EnsureSingleFeaturedPhoto::class)->handle($pet);

    expect($pet->fresh()->galleryPhotos()->map->file_name->all())->toBe(['second.jpg', 'third.jpg']);
});

test('leaves a listing with one flagged photo untouched', function () {
    $pet = Pet::factory()->create();
    $photos = attachPhotos($pet, ['cover.jpg', 'gallery.jpg'], ['cover.jpg']);

    $demoted = app(EnsureSingleFeaturedPhoto::class)->handle($pet);

    expect($demoted)->toBe(0)
        ->and($pet->fresh()->featuredPhoto()->getKey())->toBe($photos['cover.jpg']->getKey());
});

test('leaves a listing with no flagged photo without inventing a cover', function () {
    $pet = Pet::factory()->create();
    attachPhotos($pet, ['one.jpg', 'two.jpg'], []);

    $demoted = app(EnsureSingleFeaturedPhoto::class)->handle($pet);

    expect($demoted)->toBe(0)
        ->and($pet->fresh()->featuredPhoto())->toBeNull()
        ->and($pet->fresh()->galleryPhotos())->toHaveCount(2);
});

/**
 * The wiring, through the form that can actually produce the state. It is
 * `afterUpdate` rather than `beforeUpdate` because Nova invokes the media
 * field's fill callbacks — which write the custom properties — and only then
 * calls this; a `beforeUpdate` hook would run against the flags the request was
 * replacing and settle nothing.
 */
test('settles the cover when an admin ticks featured on several photos in Nova', function () {
    $admin = Admin::factory()->create();
    $pet = Pet::factory()->create();
    $photos = attachPhotos($pet, ['first.jpg', 'second.jpg', 'third.jpg'], []);

    $this->actingAs($admin, 'admin')
        ->putJson("/nova-api/pets/{$pet->getKey()}", [
            ...novaPetPayload($pet),
            '__media__' => [Pet::PHOTO_COLLECTION => $photos->map->getKey()->values()->all()],
            '__media-custom-properties__' => [Pet::PHOTO_COLLECTION => [
                [Pet::FEATURED_PROPERTY => true],
                [Pet::FEATURED_PROPERTY => true],
                [Pet::FEATURED_PROPERTY => true],
            ]],
        ])
        ->assertOk();

    expect($pet->fresh()->getMedia(Pet::PHOTO_COLLECTION, [Pet::FEATURED_PROPERTY => true])->map->getKey()->all())
        ->toBe([$photos['first.jpg']->getKey()]);
});
