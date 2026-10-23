<?php

use App\Actions\Pets\CreatePet;
use App\Enums\ListingType;
use App\Exceptions\Pets\BreedNotFound;
use App\Exceptions\Pets\CategoryNotFound;
use App\Models\Breed;
use App\Models\Category;
use App\Models\Pet;
use App\Models\User;

/**
 * The smallest validated payload the create flow accepts, plus overrides.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function createPetData(Category $category, array $overrides = []): array
{
    return [
        'name'         => 'Luna',
        'category_id'  => $category->getKey(),
        'birth_date'   => '2024-01-15',
        'gender'       => 'female',
        'color'        => 'Black',
        'description'  => 'A calm indoor cat.',
        'listing_type' => ListingType::Adoption->value,
        'status'       => 'available',
        'location'     => ['city' => 'Cairo', 'state' => 'Cairo', 'country' => 'Egypt'],
        ...$overrides,
    ];
}

test('normalizes the health repeaters to their documented shapes', function () {
    $category = Category::factory()->create();

    $pet      = app(CreatePet::class)->handle(User::factory()->create(), createPetData($category, [
        'health' => [
            'vaccinations' => [
                ['name' => 'Rabies', 'date' => '2024-01-15'],
                ['name' => 'Distemper', 'date' => ''],
            ],
            'medications'  => [['name' => 'Flea drops', 'usage' => 'Monthly']],
            'allergies'    => ['Dust', 'Pollen'],
        ],
    ]));

    // toEqual, not toBe: MySQL stores JSON object keys in its own order.
    expect($pet->vaccinations)->toEqual([
        ['name' => 'Rabies', 'date' => '2024-01-15'],
        ['name' => 'Distemper', 'date' => null],
    ])
        ->and($pet->medications)->toEqual([['name' => 'Flea drops', 'usage' => 'Monthly']])
        ->and($pet->allergies)->toBe(['Dust', 'Pollen']);
});

test('drops repeater rows with no name and stores an empty repeater as null', function () {
    $category = Category::factory()->create();

    $pet      = app(CreatePet::class)->handle(User::factory()->create(), createPetData($category, [
        'health' => [
            'vaccinations' => [['name' => '', 'date' => '2024-01-15']],
            'medications'  => [['name' => null, 'usage' => 'Monthly'], ['name' => 'Joint supplement', 'usage' => null]],
            'allergies'    => ['', null],
        ],
    ]));

    expect($pet->vaccinations)->toBeNull()
        ->and($pet->medications)->toBe([['name' => 'Joint supplement', 'usage' => null]])
        ->and($pet->allergies)->toBeNull();
});

test('capitalises each trait and re-indexes the list', function () {
    $category = Category::factory()->create();

    $pet      = app(CreatePet::class)->handle(User::factory()->create(), createPetData($category, [
        'traits' => ['friendly', '', ' playful ', 'Loyal'],
    ]));

    expect($pet->traits)->toBe(['Friendly', 'Playful', 'Loyal']);
});

test('stores the extras as a key/value map and drops pairs missing either half', function () {
    $category = Category::factory()->create();

    $pet      = app(CreatePet::class)->handle(User::factory()->create(), createPetData($category, [
        'additionalInfo' => [
            'house_trained'  => 'yes',
            'good_with_kids' => '',
            ''               => 'orphan value',
        ],
    ]));

    expect($pet->additional_info)->toBe(['house_trained' => 'yes']);
});

test('drops a price from a listing that is not a sale', function () {
    $category = Category::factory()->create();

    $pet      = app(CreatePet::class)->handle(User::factory()->create(), createPetData($category, [
        'listing_type' => ListingType::Adoption->value,
        'price'        => '500.00',
    ]));

    expect($pet->price)->toBeNull();
});

test('stores the litter counts on an adoption and derives the age from the birth date', function () {
    $category = Category::factory()->create();

    $pet      = app(CreatePet::class)->handle(User::factory()->create(), createPetData($category, [
        'name'         => null,
        'gender'       => 'mixed',
        'birth_date'   => now()->subMonths(3)->toDateString(),
        'quantity'     => 3,
        'male_count'   => 2,
        'female_count' => 1,
    ]));

    expect($pet->name)->toBeNull()
        ->and($pet->quantity)->toBe(3)
        ->and($pet->male_count)->toBe(2)
        ->and($pet->female_count)->toBe(1)
        ->and($pet->age)->toBeGreaterThan(0.2)->toBeLessThan(0.3);
});

test('writes a single animal and no sex counts for a listing that is not an adoption', function () {
    $category = Category::factory()->create();

    $pet      = app(CreatePet::class)->handle(User::factory()->create(), createPetData($category, [
        'listing_type' => ListingType::Sale->value,
        'price'        => '100',
        'quantity'     => 3,
        'male_count'   => 2,
        'female_count' => 1,
    ]));

    expect($pet->quantity)->toBe(1)
        ->and($pet->male_count)->toBeNull()
        ->and($pet->female_count)->toBeNull();
});

test('aborts with a breed_id error when the breed belongs to another category', function () {
    $category     = Category::factory()->create();
    $foreignBreed = Breed::factory()->for(Category::factory())->create();

    $handle       = fn () => app(CreatePet::class)->handle(
        User::factory()->create(),
        createPetData($category, ['breed_id' => $foreignBreed->getKey()]),
    );

    expect($handle)->toThrow(BreedNotFound::class);
    expect(Pet::query()->count())->toBe(0);
});

test('aborts with a category_id error when the category no longer exists', function () {
    $category = Category::factory()->create();
    $data     = createPetData($category);
    $category->delete();

    $handle   = fn () => app(CreatePet::class)->handle(User::factory()->create(), $data);

    expect($handle)->toThrow(CategoryNotFound::class);
    expect(Pet::query()->count())->toBe(0);
});
