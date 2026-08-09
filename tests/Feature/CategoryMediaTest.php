<?php

use App\Http\Resources\CategoryResource;
use App\Models\Breed;
use App\Models\Category;
use App\Models\Pet;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function categoryMediaFakePng(string $name = 'image.png'): Illuminate\Http\Testing\File
{
    $png = base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=',
        true
    );

    return UploadedFile::fake()
        ->createWithContent($name, (string) $png)
        ->mimeType('image/png');
}

it('stores category media without requiring a user owner', function () {
    Storage::fake('public');

    $category = Category::factory()->create();

    $category
        ->addMedia(categoryMediaFakePng('dogs.png'))
        ->toMediaCollection('categories');

    $media = $category->getFirstMedia('categories');

    expect($media)->not->toBeNull()
        ->and($media->getPathRelativeToRoot())
        ->toStartWith("media/category/{$category->id}/");
});

it('stores breed media without requiring a user owner', function () {
    Storage::fake('public');

    $breed = Breed::factory()->create();

    $breed
        ->addMedia(categoryMediaFakePng('husky.png'))
        ->toMediaCollection('breeds');

    $media = $breed->getFirstMedia('breeds');

    expect($media)->not->toBeNull()
        ->and($media->getPathRelativeToRoot())
        ->toStartWith("media/breed/{$breed->id}/");
});

it('keeps pet media under the owning user directory', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $category = Category::factory()->create();
    $breed = Breed::factory()->create(['category_id' => $category->id]);
    $pet = Pet::factory()->create([
        'user_id' => $user->id,
        'category_id' => $category->id,
        'breed_id' => $breed->id,
        'status' => 'available',
    ]);

    $pet
        ->addMedia(categoryMediaFakePng('pet.png'))
        ->toMediaCollection('pets');

    $media = $pet->getFirstMedia('pets');

    expect($media)->not->toBeNull()
        ->and($media->getPathRelativeToRoot())
        ->toStartWith("media/{$user->media_directory_name}/pet/{$pet->id}/");
});

it('exposes the category media url through the category resource', function () {
    Storage::fake('public');

    $category = Category::factory()->create(['image' => null]);

    $category
        ->addMedia(categoryMediaFakePng('dogs.png'))
        ->toMediaCollection('categories');

    $resource = CategoryResource::make($category->fresh())->resolve();

    expect($resource['image'])->not->toBeEmpty()
        ->and($resource['image'])->toContain('/storage/');
});
