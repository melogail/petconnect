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
        'name' => 'Luna',
        'category_id' => $category->getKey(),
        'age' => '2',
        'gender' => 'female',
        'color' => 'Black',
        'description' => 'A calm indoor cat.',
        'listing_type' => ListingType::Adoption->value,
        'status' => 'available',
        'location' => ['city' => 'Cairo', 'state' => 'Cairo', 'country' => 'Egypt'],
        ...$overrides,
    ];
}

test('normalizes the health repeaters to their documented shapes', function () {
    $category = Category::factory()->create();

    $pet = app(CreatePet::class)->handle(User::factory()->create(), createPetData($category, [
        'health' => [
            'vaccinations' => [
                ['name' => 'Rabies', 'date' => '2024-01-15'],
                ['name' => 'Distemper', 'date' => ''],
            ],
            'medications' => [['name' => 'Flea drops', 'usage' => 'Monthly']],
            'allergies' => ['Dust', 'Pollen'],
        ],
    ]));

    expect($pet->vaccinations)->toBe([
        ['name' => 'Rabies', 'date' => '2024-01-15'],
        ['name' => 'Distemper', 'date' => null],
    ])
        ->and($pet->medications)->toBe([['name' => 'Flea drops', 'usage' => 'Monthly']])
        ->and($pet->allergies)->toBe(['Dust', 'Pollen']);
});

test('drops repeater rows with no name and stores an empty repeater as null', function () {
    $category = Category::factory()->create();

    $pet = app(CreatePet::class)->handle(User::factory()->create(), createPetData($category, [
        'health' => [
            'vaccinations' => [['name' => '', 'date' => '2024-01-15']],
            'medications' => [['name' => null, 'usage' => 'Monthly'], ['name' => 'Joint supplement', 'usage' => null]],
            'allergies' => ['', null],
        ],
    ]));

    expect($pet->vaccinations)->toBeNull()
        ->and($pet->medications)->toBe([['name' => 'Joint supplement', 'usage' => null]])
        ->and($pet->allergies)->toBeNull();
});

test('capitalises each trait and re-indexes the list', function () {
    $category = Category::factory()->create();

    $pet = app(CreatePet::class)->handle(User::factory()->create(), createPetData($category, [
        'traits' => ['friendly', '', ' playful ', 'Loyal'],
    ]));

    expect($pet->traits)->toBe(['Friendly', 'Playful', 'Loyal']);
});

test('stores the extras as a key/value map and drops pairs missing either half', function () {
    $category = Category::factory()->create();

    $pet = app(CreatePet::class)->handle(User::factory()->create(), createPetData($category, [
        'additionalInfo' => [
            'house_trained' => 'yes',
            'good_with_kids' => '',
            '' => 'orphan value',
        ],
    ]));

    expect($pet->additional_info)->toBe(['house_trained' => 'yes']);
});

test('drops a price from a listing that is not a sale', function () {
    $category = Category::factory()->create();

    $pet = app(CreatePet::class)->handle(User::factory()->create(), createPetData($category, [
        'listing_type' => ListingType::Adoption->value,
        'price' => '500.00',
    ]));

    expect($pet->price)->toBeNull();
});

test('aborts with a breed_id error when the breed belongs to another category', function () {
    $category = Category::factory()->create();
    $foreignBreed = Breed::factory()->for(Category::factory())->create();

    $handle = fn () => app(CreatePet::class)->handle(
        User::factory()->create(),
        createPetData($category, ['breed_id' => $foreignBreed->getKey()]),
    );

    expect($handle)->toThrow(BreedNotFound::class);
    expect(Pet::query()->count())->toBe(0);
});

test('aborts with a category_id error when the category no longer exists', function () {
    $category = Category::factory()->create();
    $data = createPetData($category);
    $category->delete();

    $handle = fn () => app(CreatePet::class)->handle(User::factory()->create(), $data);

    expect($handle)->toThrow(CategoryNotFound::class);
    expect(Pet::query()->count())->toBe(0);
});
