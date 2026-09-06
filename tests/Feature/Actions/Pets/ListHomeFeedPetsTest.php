<?php

use App\Actions\Pets\ListHomeFeedPets;
use App\Enums\ListingType;
use App\Models\Breed;
use App\Models\Category;
use App\Models\Pet;

/**
 * The ids the feed returned, in the order it returned them.
 *
 * @param  array<string, mixed>  $filters
 * @return list<int>
 */
function feedIds(array $filters = [], ?float $latitude = null, ?float $longitude = null, ?float $radiusKm = null): array
{
    return app(ListHomeFeedPets::class)
        ->handle(filters: $filters, latitude: $latitude, longitude: $longitude, radiusKm: $radiusKm)
        ->pluck('id')
        ->all();
}

test('only available listings reach the feed', function () {
    $available = Pet::factory()->available()->create();
    Pet::factory()->unavailable()->create();

    expect(feedIds())->toBe([$available->getKey()]);
});

test('the category filter keeps only listings in those categories', function () {
    $category = Category::factory()->create();
    $wanted = Pet::factory()->available()->for($category)->create();
    Pet::factory()->available()->create();

    expect(feedIds(['category_ids' => [$category->getKey()]]))->toBe([$wanted->getKey()]);
});

test('the breed filter keeps only listings of those breeds', function () {
    $category = Category::factory()->create();
    $breed = Breed::factory()->for($category)->create();
    $wanted = Pet::factory()->available()->for($category)->for($breed)->create();
    Pet::factory()->available()->for($category)->create();

    expect(feedIds(['breed_ids' => [$breed->getKey()]]))->toBe([$wanted->getKey()]);
});

test('a category and a breed filter together are ORed, not intersected', function () {
    $dogs = Category::factory()->create();
    $cats = Category::factory()->create();
    $siamese = Breed::factory()->for($cats)->create();

    $dog = Pet::factory()->available()->for($dogs)->create();
    $siameseCat = Pet::factory()->available()->for($cats)->for($siamese)->create();
    $otherCat = Pet::factory()->available()->for($cats)->create();

    $ids = feedIds([
        'category_ids' => [$dogs->getKey()],
        'breed_ids' => [$siamese->getKey()],
    ]);

    expect($ids)->toHaveCount(2)
        ->and($ids)->toContain($dog->getKey(), $siameseCat->getKey())
        ->and($ids)->not->toContain($otherCat->getKey());
});

test('the age range filter compares ages numerically rather than as strings', function () {
    $puppy = Pet::factory()->available()->create(['age' => '0.5']);
    $adult = Pet::factory()->available()->create(['age' => '2']);
    $senior = Pet::factory()->available()->create(['age' => '10']);

    $ids = feedIds(['age_min' => 1, 'age_max' => 3]);

    expect($ids)->toBe([$adult->getKey()])
        ->and($ids)->not->toContain($puppy->getKey(), $senior->getKey());
});

test('the listing type filter keeps only listings offered that way', function () {
    $forSale = Pet::factory()->available()->forSale()->create();
    Pet::factory()->available()->adoption()->create();
    Pet::factory()->available()->mating()->create();

    expect(feedIds(['listing_types' => [ListingType::Sale->value]]))->toBe([$forSale->getKey()]);
});

test('the vaccinated filter only narrows when the visitor expressed a preference', function () {
    $vaccinated = Pet::factory()->available()->create(['vaccinated' => true]);
    $unvaccinated = Pet::factory()->available()->create(['vaccinated' => false]);

    expect(feedIds(['vaccinated' => true]))->toBe([$vaccinated->getKey()])
        ->and(feedIds())->toContain($vaccinated->getKey(), $unvaccinated->getKey());
});

test('coordinates order the feed by distance and drop listings outside the radius', function () {
    $cairo = Pet::factory()->available()->at(30.0444, 31.2357)->create();
    $giza = Pet::factory()->available()->at(30.0131, 31.2089)->create();
    $luxor = Pet::factory()->available()->at(25.6872, 32.6396)->create();

    $ids = feedIds(latitude: 30.0444, longitude: 31.2357, radiusKm: 20);

    expect($ids)->toBe([$cairo->getKey(), $giza->getKey()])
        ->and($ids)->not->toContain($luxor->getKey());
});

test('without coordinates the feed is ordered newest first', function () {
    $older = Pet::factory()->available()->create(['created_at' => now()->subDay()]);
    $newer = Pet::factory()->available()->create(['created_at' => now()]);

    expect(feedIds())->toBe([$newer->getKey(), $older->getKey()]);
});
