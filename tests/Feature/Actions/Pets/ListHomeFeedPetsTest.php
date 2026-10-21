<?php

use App\Actions\Pets\ListHomeFeedPets;
use App\Enums\ListingType;
use App\Models\Breed;
use App\Models\Category;
use App\Models\Pet;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

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
    $wanted   = Pet::factory()->available()->for($category)->create();
    Pet::factory()->available()->create();

    expect(feedIds(['category_ids' => [$category->getKey()]]))->toBe([$wanted->getKey()]);
});

test('the breed filter keeps only listings of those breeds', function () {
    $category = Category::factory()->create();
    $breed    = Breed::factory()->for($category)->create();
    $wanted   = Pet::factory()->available()->for($category)->for($breed)->create();
    Pet::factory()->available()->for($category)->create();

    expect(feedIds(['breed_ids' => [$breed->getKey()]]))->toBe([$wanted->getKey()]);
});

test('a category and a breed filter together are ORed, not intersected', function () {
    $dogs       = Category::factory()->create();
    $cats       = Category::factory()->create();
    $siamese    = Breed::factory()->for($cats)->create();

    $dog        = Pet::factory()->available()->for($dogs)->create();
    $siameseCat = Pet::factory()->available()->for($cats)->for($siamese)->create();
    $otherCat   = Pet::factory()->available()->for($cats)->create();

    $ids        = feedIds([
        'category_ids' => [$dogs->getKey()],
        'breed_ids'    => [$siamese->getKey()],
    ]);

    expect($ids)->toHaveCount(2)
        ->and($ids)->toContain($dog->getKey(), $siameseCat->getKey())
        ->and($ids)->not->toContain($otherCat->getKey());
});

test('the age range filter is a window on the birth date and skips listings without one', function () {
    $puppy   = Pet::factory()->available()->create(['birth_date' => now()->subMonths(6)->toDateString()]);
    $adult   = Pet::factory()->available()->create(['birth_date' => now()->subYears(2)->toDateString()]);
    $senior  = Pet::factory()->available()->create(['birth_date' => now()->subYears(10)->toDateString()]);
    $unknown = Pet::factory()->available()->create(['birth_date' => null]);

    $ids     = feedIds(['age_min' => 1, 'age_max' => 3]);

    expect($ids)->toBe([$adult->getKey()])
        ->and($ids)->not->toContain($puppy->getKey(), $senior->getKey(), $unknown->getKey());
});

test('a listing with no birth date stays in the feed while no age bound is set', function () {
    $unknown = Pet::factory()->available()->create(['birth_date' => null]);

    expect(feedIds([]))->toContain($unknown->getKey());
});

test('the listing type filter keeps only listings offered that way', function () {
    $forSale = Pet::factory()->available()->forSale()->create();
    Pet::factory()->available()->adoption()->create();
    Pet::factory()->available()->mating()->create();

    expect(feedIds(['listing_types' => [ListingType::Sale->value]]))->toBe([$forSale->getKey()]);
});

test('the vaccinated filter only narrows when the visitor expressed a preference', function () {
    $vaccinated   = Pet::factory()->available()->create(['vaccinated' => true]);
    $unvaccinated = Pet::factory()->available()->create(['vaccinated' => false]);

    expect(feedIds(['vaccinated' => true]))->toBe([$vaccinated->getKey()])
        ->and(feedIds())->toContain($vaccinated->getKey(), $unvaccinated->getKey());
});

test('coordinates order the feed by distance and drop listings outside the radius', function () {
    $cairo = Pet::factory()->available()->at(30.0444, 31.2357)->create();
    $giza  = Pet::factory()->available()->at(30.0131, 31.2089)->create();
    $luxor = Pet::factory()->available()->at(25.6872, 32.6396)->create();

    $ids   = feedIds(latitude: 30.0444, longitude: 31.2357, radiusKm: 20);

    expect($ids)->toBe([$cairo->getKey(), $giza->getKey()])
        ->and($ids)->not->toContain($luxor->getKey());
});

test('the search term matches the listing name as a substring, case-insensitively', function () {
    $luna = Pet::factory()->available()->create(['name' => 'Luna Belle']);
    Pet::factory()->available()->create(['name' => 'Max']);

    expect(feedIds(['search' => 'luna']))->toBe([$luna->getKey()]);
});

test('the search term matches a breed name in either language', function () {
    $category  = Category::factory()->create(['name' => 'Dogs', 'name_ar' => 'كلاب']);
    $retriever = Breed::factory()->for($category)->create(['name' => 'Golden Retriever', 'name_ar' => 'جولدن ريتريفر']);
    $poodle    = Breed::factory()->for($category)->create(['name' => 'Poodle', 'name_ar' => 'بودل']);
    $golden    = Pet::factory()->available()->for($category)->for($retriever)->create(['name' => 'Max']);
    Pet::factory()->available()->for($category)->for($poodle)->create(['name' => 'Rex']);

    expect(feedIds(['search' => 'retriever']))->toBe([$golden->getKey()])
        ->and(feedIds(['search' => 'جولدن']))->toBe([$golden->getKey()]);
});

test('the search term matches a category name, which is how a nameless litter is found', function () {
    $cats   = Category::factory()->create(['name' => 'Cats', 'name_ar' => 'قطط']);
    $dogs   = Category::factory()->create(['name' => 'Dogs', 'name_ar' => 'كلاب']);
    $litter = Pet::factory()->available()->for($cats)->litter(2, 1)->create();
    Pet::factory()->available()->for($dogs)->create(['name' => 'Rex']);

    expect(feedIds(['search' => 'cats']))->toBe([$litter->getKey()])
        ->and(feedIds(['search' => 'قطط']))->toBe([$litter->getKey()]);
});

test('a search term is text, not a pattern: wildcards in it do not match everything', function () {
    Pet::factory()->available()->create(['name' => 'Luna']);
    $odd = Pet::factory()->available()->create(['name' => '100% Good Boy']);

    expect(feedIds(['search' => '%']))->toBe([$odd->getKey()])
        ->and(feedIds(['search' => '_']))->toBe([]);
});

test('a backslash or the escape character in a search term is matched as text', function () {
    Pet::factory()->available()->create(['name' => 'Luna']);
    $slashed = Pet::factory()->available()->create(['name' => 'Rex \\ Jr']);
    $banged  = Pet::factory()->available()->create(['name' => 'Wow! Max']);

    expect(feedIds(['search' => '\\']))->toBe([$slashed->getKey()])
        ->and(feedIds(['search' => '!']))->toBe([$banged->getKey()])
        ->and(feedIds(['search' => '!%']))->toBe([]);
});

/**
 * The suite runs on SQLite, which accepted `ESCAPE '\'`; MySQL reads that
 * backslash as escaping the quote and refused the whole statement with a 1064
 * every time the search box was used. Pinning the compiled clause is the only
 * way this suite can see the difference. Counterfactual: put the backslash
 * back as the escape character and this fails while every other search test
 * stays green.
 */
test('the search escape clause contains no backslash, so the statement parses on MySQL as well as SQLite', function () {
    Pet::factory()->available()->create(['name' => 'Luna']);

    $statements = [];
    DB::listen(function (QueryExecuted $query) use (&$statements): void {
        $statements[] = $query->sql;
    });

    feedIds(['search' => 'luna']);

    $searching  = array_values(array_filter($statements, fn (string $sql): bool => str_contains($sql, 'ESCAPE')));

    expect($searching)->not->toBeEmpty();

    foreach ($searching as $sql) {
        expect($sql)->toContain("LIKE ? ESCAPE '!'")
            ->and($sql)->not->toContain('\\');
    }
});

test('a blank search term leaves the feed unfiltered', function () {
    $pet = Pet::factory()->available()->create();

    expect(feedIds(['search' => '   ']))->toBe([$pet->getKey()]);
});

test('without coordinates the feed is ordered newest first', function () {
    $older = Pet::factory()->available()->create(['created_at' => now()->subDay()]);
    $newer = Pet::factory()->available()->create(['created_at' => now()]);

    expect(feedIds())->toBe([$newer->getKey(), $older->getKey()]);
});
