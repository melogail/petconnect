<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePetRequest;
use App\Http\Requests\UpdatePetRequest;
use App\Http\Resources\CreatePetPostResource;
use App\Models\Category;
use App\Models\Pet;
use App\Services\PetService;
use App\Enums\ListingType;

class PetController extends Controller
{

    public function __construct(protected PetService $petService)
    {
        //
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $petCategories = CreatePetPostResource::collection(Category::with('breeds')->get());

        return inertia('pet/Create', [
            'petCategories' => $petCategories,
            'listingTypes' => ListingType::options(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePetRequest $request)
    {
        $this->petService->createPet($request);

        return redirect()->route('home')->with('success', 'Pet created successfully');
    }

    /**
     * Display the specified resource.
     */
    public function show($pet)
    {
        return inertia('pet/Show', [
            'pet' => $pet,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Pet $pet)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePetRequest $request, Pet $pet)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Pet $pet)
    {
        //
    }
}
