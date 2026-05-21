<?php

namespace Tests\Feature\Services;

use App\Enums\ListingType;
use App\Http\Resources\Pet\PetDetailResource;
use App\Models\Breed;
use App\Models\Category;
use App\Models\Pet;
use App\Models\User;
use App\Services\PetService;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PetServiceTest extends TestCase
{
    use RefreshDatabase;

    protected PetService $petService;

    private function fakePng(string $name = 'pet.png'): \Illuminate\Http\Testing\File
    {
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=',
            true
        );

        return \Illuminate\Http\UploadedFile::fake()
            ->createWithContent($name, (string) $png)
            ->mimeType('image/png');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CategorySeeder::class);
        $this->petService = $this->app->make(PetService::class);
    }

    public function test_update_pet_capitalizes_traits()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Create an initial pet
        $pet = Pet::factory()->create([
            'user_id' => $user->id,
            'category_id' => Category::first()->id,
            'traits' => ['Initial', 'Traits'],
        ]);

        // Request data with lowercase traits
        $data = [
            'name' => 'Updated Name',
            'type' => $pet->category_id,
            'breed' => $pet->breed_id,
            'age' => 3,
            'gender' => 'male',
            'color' => 'Brown',
            'weight' => 20,
            'description' => 'A very good boy.',
            'listing_type' => ListingType::Adoption->value,
            'status' => 'available',
            'traits' => ['friendly', 'loyal', 'smart'],
            'location' => [
                'city' => 'Anytown',
                'state' => 'State',
                'country' => 'USA',
            ],
            // Mock featured image
            'featuredImage' => $this->fakePng(),
        ];

        // Perform the update via PUT request
        $response = $this->put(route('pets.update', $pet), $data);

        $response->assertRedirect();

        $pet->refresh();

        // Assert that traits are capitalized
        // Note: The traits might be stored as JSON or array depending on the model's $casts
        $savedTraits = is_string($pet->traits) ? json_decode($pet->traits, true) : $pet->traits;

        $this->assertEquals(['Friendly', 'Loyal', 'Smart'], $savedTraits);
        $this->assertEquals('Updated Name', $pet->name);
    }

    public function test_create_pet_capitalizes_traits()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $category = Category::first();
        $breed = Breed::factory()->create(['category_id' => $category->id]);

        $data = [
            'name' => 'New Pet',
            'type' => $category->id,
            'breed' => $breed->id,
            'age' => 2,
            'gender' => 'female',
            'color' => 'White',
            'weight' => 10,
            'description' => 'A sweet girl.',
            'listing_type' => ListingType::Adoption->value,
            'status' => 'available',
            'traits' => ['playful', 'gentle'],
            'location' => [
                'city' => 'Anytown',
                'state' => 'State',
                'country' => 'USA',
            ],
            'featuredImage' => $this->fakePng(),
        ];

        $response = $this->post(route('pets.store'), $data);

        $response->assertRedirect();

        $pet = Pet::where('name', 'New Pet')->first();
        $this->assertNotNull($pet);

        $savedTraits = is_string($pet->traits) ? json_decode($pet->traits, true) : $pet->traits;

        $this->assertEquals(['Playful', 'Gentle'], $savedTraits);
    }

    public function test_create_pet_persists_json_columns_as_arrays()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $category = Category::first();
        $breed = Breed::factory()->create(['category_id' => $category->id]);

        $data = [
            'name' => 'Json Pet',
            'type' => $category->id,
            'breed' => $breed->id,
            'age' => 2,
            'gender' => 'female',
            'color' => 'White',
            'weight' => 10,
            'description' => 'A sweet girl.',
            'listing_type' => ListingType::Adoption->value,
            'status' => 'available',
            'traits' => ['playful', 'gentle'],
            'location' => [
                'city' => 'Anytown',
                'state' => 'State',
                'country' => 'USA',
            ],
            'health' => [
                'vaccinations' => [
                    ['date' => '2024-01-01', 'name' => 'Rabies'],
                ],
                'medications' => [
                    ['name' => 'Heartworm Prevention', 'usage' => 'Monthly'],
                ],
                'allergies' => ['Chicken'],
            ],
            'additionalInfo' => [
                ['key' => 'Microchip', 'value' => '12345'],
            ],
            'featuredImage' => $this->fakePng(),
        ];

        $response = $this->post(route('pets.store'), $data);

        $response->assertRedirect();

        $pet = Pet::where('name', 'Json Pet')->firstOrFail();

        $this->assertSame([['date' => '2024-01-01', 'name' => 'Rabies']], $pet->vaccinations);
        $this->assertSame([['name' => 'Heartworm Prevention', 'usage' => 'Monthly']], $pet->medications);
        $this->assertSame(['Chicken'], $pet->allergies);
        $this->assertSame(['Playful', 'Gentle'], $pet->traits);
        $this->assertSame([['key' => 'Microchip', 'value' => '12345']], $pet->additional_info);
        $this->assertIsArray(json_decode($pet->getRawOriginal('additional_info'), true));
    }

    public function test_update_pet_without_new_featured_image_keeps_existing()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Create an initial pet with a mocked featured image
        $pet = Pet::factory()->create([
            'user_id' => $user->id,
            'category_id' => Category::first()->id,
        ]);

        $pet->addMedia($this->fakePng('original.png'))
            ->withCustomProperties(['featured' => true])
            ->toMediaCollection('pets');

        $this->assertCount(1, $pet->getMedia('pets'));

        // Request data WITHOUT featuredImage
        $data = [
            'name' => 'Name Update Only',
            'type' => $pet->category_id,
            'breed' => $pet->breed_id,
            'age' => 4,
            'gender' => 'female',
            'color' => 'Black',
            'weight' => 25,
            'description' => 'Updated description.',
            'listing_type' => ListingType::Adoption->value,
            'status' => 'available',
            'location' => [
                'city' => 'NewCity',
                'state' => 'NewState',
                'country' => 'USA',
            ],
        ];

        // Perform the update via PUT request
        $response = $this->put(route('pets.update', $pet), $data);

        $response->assertRedirect();

        $pet->refresh();

        // Assert name updated but image remains
        $this->assertEquals('Name Update Only', $pet->name);
        $this->assertCount(1, $pet->getMedia('pets'));
        $this->assertEquals('original.png', $pet->getFirstMedia('pets')->file_name);
    }

    public function test_update_pet_persists_last_vet_visit()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $pet = Pet::factory()->create([
            'user_id' => $user->id,
            'category_id' => Category::first()->id,
            'last_vet_visit' => null,
        ]);

        $pet->addMedia($this->fakePng('original.png'))
            ->withCustomProperties(['featured' => true])
            ->toMediaCollection('pets');

        $data = [
            'name' => $pet->name,
            'type' => $pet->category_id,
            'breed' => $pet->breed_id,
            'age' => 4,
            'gender' => 'female',
            'color' => 'Black',
            'weight' => 25,
            'description' => 'Updated description.',
            'listing_type' => ListingType::Adoption->value,
            'status' => 'available',
            'health' => [
                'status' => 'good',
                'vaccinated' => true,
                'spayedNeutered' => true,
                'specialNeeds' => '',
                'lastVetVisit' => '2024-08-21',
            ],
            'location' => [
                'city' => 'NewCity',
                'state' => 'NewState',
                'country' => 'USA',
            ],
        ];

        $response = $this->put(route('pets.update', $pet), $data);

        $response->assertRedirect();

        $pet->refresh();

        $this->assertNotNull($pet->last_vet_visit);
        $this->assertSame('2024-08-21', $pet->last_vet_visit->format('Y-m-d'));
    }

    public function test_update_pet_can_clear_additional_info()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $pet = Pet::factory()->create([
            'user_id' => $user->id,
            'category_id' => Category::first()->id,
            'additional_info' => [
                ['key' => 'Microchip', 'value' => '12345'],
            ],
        ]);

        $data = [
            'name' => $pet->name,
            'type' => $pet->category_id,
            'breed' => $pet->breed_id,
            'age' => 4,
            'gender' => 'female',
            'color' => 'Black',
            'weight' => 25,
            'description' => 'Updated description.',
            'listing_type' => ListingType::Adoption->value,
            'status' => 'available',
            'location' => [
                'city' => 'NewCity',
                'state' => 'NewState',
                'country' => 'USA',
            ],
            'additionalInfo' => [],
        ];

        $response = $this->put(route('pets.update', $pet), $data);

        $response->assertRedirect();

        $pet->refresh();

        $this->assertNull($pet->additional_info);
    }

    public function test_pet_detail_resource_formats_last_vet_visit_as_y_m_d()
    {
        $user = User::factory()->create();
        $pet = Pet::factory()->create([
            'user_id' => $user->id,
            'category_id' => Category::first()->id,
            'last_vet_visit' => '2024-05-10',
        ]);

        $array = PetDetailResource::make($pet->fresh())->toArray(request());

        $this->assertSame('2024-05-10', $array['last_vet_visit']);
    }
}
