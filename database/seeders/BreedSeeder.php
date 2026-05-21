<?php

namespace Database\Seeders;

use App\Models\Breed;
use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BreedSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $breeds = [
            'Dogs' => [
                'Golden Retriever', 'German Shepherd', 'Labrador Retriever', 'French Bulldog',
                'Bulldog', 'Poodle', 'Beagle', 'Rottweiler', 'Yorkshire Terrier', 'Boxer',
                'Dachshund', 'Siberian Husky', 'Great Dane', 'Doberman Pinscher', 'Shih Tzu',
            ],
            'Cats' => [
                'Persian Cat', 'Siamese Cat', 'Maine Coon', 'Bengal Cat', 'Ragdoll',
                'British Shorthair', 'Abyssinian', 'Sphynx', 'Scottish Fold', 'Birman',
                'Russian Blue', 'American Shorthair', 'Oriental', 'Devon Rex',
            ],
            'Birds' => [
                'Budgie', 'Cockatiel', 'African Grey Parrot', 'Macaw', 'Canary',
                'Lovebird', 'Conure', 'Cockatoo', 'Finch', 'Parakeet',
            ],
            'Fish' => [
                'Goldfish', 'Betta Fish', 'Guppy', 'Angelfish', 'Tetra',
                'Molly', 'Platy', 'Swordtail', 'Discus', 'Oscar',
            ],
            'Small Pets' => [
                'Rabbit', 'Hamster', 'Guinea Pig', 'Chinchilla', 'Ferret',
                'Gerbil', 'Rat', 'Mouse', 'Hedgehog',
            ],
            'Reptiles' => [
                'Bearded Dragon', 'Leopard Gecko', 'Ball Python', 'Corn Snake',
                'Red-Eared Slider Turtle', 'Iguana', 'Chameleon', 'Blue Tongued Skink',
            ],
            'Farm Animals' => [
                'Chicken', 'Duck', 'Goat', 'Sheep', 'Pig', 'Horse', 'Cow', 'Turkey',
            ],
        ];

        foreach ($breeds as $categoryName => $breedList) {
            $category = Category::where('name', $categoryName)->first();

            if ($category) {
                foreach ($breedList as $breedName) {
                    Breed::create([
                        'category_id' => $category->id,
                        'name' => $breedName,
                        'slug' => Str::slug($breedName),
                        'description' => "A popular {$categoryName} breed.",
                    ]);
                }
            }
        }
    }
}
