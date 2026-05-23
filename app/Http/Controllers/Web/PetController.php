<?php

namespace App\Http\Controllers\Web;

use App\Enums\ListingType;
use App\Enums\ReportReason;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePetRequest;
use App\Http\Requests\UpdatePetRequest;
use App\Http\Resources\CreatePetPostResource;
use App\Http\Resources\Pet\PetDetailResource;
use App\Models\Category;
use App\Models\Pet;
use App\Services\PetService;
use Illuminate\Http\RedirectResponse;

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
        $this->authorize('create', Pet::class);

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
    public function show(Pet $pet)
    {
        $pet->load([
            'user',
            'category',
            'breed',
            'comments' => fn ($query) => $query
                ->whereNull('parent_id')
                ->with([
                    'user',
                    'replies' => fn ($replyQuery) => $replyQuery
                        ->with('user')
                        ->withReportedByCurrentUser(),
                ])
                ->withReportedByCurrentUser()
                ->latest(),
        ]);

        return inertia('pet/Show', [
            'pet' => PetDetailResource::make($pet),
            'reportReasons' => ReportReason::options(),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Pet $pet)
    {
        $petCategories = CreatePetPostResource::collection(Category::with('breeds')->get());

        return inertia('pet/Edit', [
            'pet' => PetDetailResource::make($pet->load('user', 'category', 'breed', 'comments')),
            'petCategories' => $petCategories,
            'listingTypes' => ListingType::options(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePetRequest $request, Pet $pet)
    {
        $this->petService->updatePet($pet->id, $request);

        return redirect()->route('pets.show', $pet)->with('success', 'Pet updated successfully');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Pet $pet): RedirectResponse
    {
        $this->authorize('delete', $pet);
        $this->petService->deletePet($pet->id);

        return redirect()->route('home')->with('success', 'Pet listing removed successfully');
    }

    public function toggleStatus(Pet $pet): RedirectResponse
    {
        $this->authorize('update', $pet);
        $this->petService->toggleStatus($pet->id);

        return back()->with('success', 'Pet status updated successfully');
    }
}
