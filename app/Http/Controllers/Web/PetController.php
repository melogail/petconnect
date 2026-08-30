<?php

namespace App\Http\Controllers\Web;

use App\Actions\Pets\CreatePet;
use App\Actions\Pets\DeletePet;
use App\Actions\Pets\ListPetCategories;
use App\Actions\Pets\LoadPetDetail;
use App\Actions\Pets\RecordPetView;
use App\Actions\Pets\TogglePetLike;
use App\Actions\Pets\TogglePetStatus;
use App\Actions\Pets\UpdatePet;
use App\Enums\HealthStatus;
use App\Enums\ListingType;
use App\Enums\PetGender;
use App\Enums\PetStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pet\StorePetRequest;
use App\Http\Requests\Pet\UpdatePetRequest;
use App\Http\Resources\Pet\PetCategoryOptionResource;
use App\Http\Resources\Pet\PetDetailResource;
use App\Models\Pet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pet listings.
 *
 * Every action authorizes through PetPolicy and then hands the work to one
 * Action or pipeline; no query or business rule lives here.
 */
class PetController extends Controller
{
    /**
     * Show the form for publishing a listing.
     */
    public function create(ListPetCategories $listPetCategories): Response
    {
        $this->authorize('create', Pet::class);

        return Inertia::render('pets/Create', [
            'categories' => PetCategoryOptionResource::collection($listPetCategories->handle()),
            'listingTypes' => ListingType::options(),
            'statuses' => PetStatus::options(),
            'genders' => PetGender::options(),
            'healthStatuses' => HealthStatus::options(),
        ]);
    }

    /**
     * Publish a listing.
     */
    public function store(StorePetRequest $request, CreatePet $createPet): RedirectResponse
    {
        $this->authorize('create', Pet::class);

        $pet = $createPet->handle(
            owner: $request->user(),
            data: $request->validated(),
            featuredImage: $request->featuredImage(),
            galleryImages: $request->galleryImages(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Listing published.')]);

        return to_route('pets.show', $pet);
    }

    /**
     * Show a listing. Public: guests reach a shared link without signing in.
     */
    public function show(
        Request $request,
        Pet $pet,
        LoadPetDetail $loadPetDetail,
        RecordPetView $recordPetView,
    ): Response {
        $this->authorize('view', $pet);

        $recordPetView->handle($pet, $request->user(), $this->visitorKey($request));

        return Inertia::render('pets/Show', [
            'pet' => PetDetailResource::make($loadPetDetail->handle($pet, $request->user())),
        ]);
    }

    /**
     * Show the form for editing a listing.
     *
     * The ownership check is the one the legacy edit action was missing: it
     * rendered the full record — veterinarian contact details, exact
     * coordinates, medications — for any verified account that knew the id.
     */
    public function edit(
        Request $request,
        Pet $pet,
        LoadPetDetail $loadPetDetail,
        ListPetCategories $listPetCategories,
    ): Response {
        $this->authorize('update', $pet);

        return Inertia::render('pets/Edit', [
            'pet' => PetDetailResource::make($loadPetDetail->handle($pet, $request->user())),
            'categories' => PetCategoryOptionResource::collection($listPetCategories->handle()),
            'listingTypes' => ListingType::options(),
            'statuses' => PetStatus::options(),
            'genders' => PetGender::options(),
            'healthStatuses' => HealthStatus::options(),
        ]);
    }

    /**
     * Apply an edit to a listing.
     */
    public function update(UpdatePetRequest $request, Pet $pet, UpdatePet $updatePet): RedirectResponse
    {
        $this->authorize('update', $pet);

        $updatePet->handle(
            pet: $pet,
            data: $request->validated(),
            featuredImage: $request->featuredImage(),
            galleryImages: $request->galleryImages(),
            deletedMediaIds: $request->deletedMediaIds(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Listing updated.')]);

        return to_route('pets.show', $pet);
    }

    /**
     * Retire a listing.
     */
    public function destroy(Pet $pet, DeletePet $deletePet): RedirectResponse
    {
        $this->authorize('delete', $pet);

        $deletePet->handle($pet);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Listing removed.')]);

        return to_route('home');
    }

    /**
     * Flip a listing between available and unavailable.
     */
    public function toggleStatus(Pet $pet, TogglePetStatus $togglePetStatus): RedirectResponse
    {
        $this->authorize('update', $pet);

        $status = $togglePetStatus->handle($pet);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Listing marked as :status.', ['status' => $status->label()]),
        ]);

        return back();
    }

    /**
     * Toggle the viewer's like on a listing.
     */
    public function toggleLike(Request $request, Pet $pet, TogglePetLike $togglePetLike): RedirectResponse
    {
        $this->authorize('like', $pet);

        $togglePetLike->handle($pet, $request->user());

        return back();
    }

    /**
     * The key a view is deduplicated against.
     *
     * A signed-in visitor is keyed by id so the dedup follows them across
     * devices; a guest by session id, which survives a changing IP and does not
     * lump every visitor behind one NAT together.
     */
    private function visitorKey(Request $request): string
    {
        return (string) ($request->user()?->getKey() ?? $request->session()->getId());
    }
}
