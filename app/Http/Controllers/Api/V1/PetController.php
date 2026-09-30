<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Likes\CountLikes;
use App\Actions\Likes\ToggleLike;
use App\Actions\Pets\CreatePet;
use App\Actions\Pets\DeletePet;
use App\Actions\Pets\ListHomeFeedPets;
use App\Actions\Pets\LoadPetDetail;
use App\Actions\Pets\RecordPetView;
use App\Actions\Pets\TogglePetStatus;
use App\Actions\Pets\UpdatePet;
use App\Concerns\ResolvesRequestUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pet\ListHomeFeedRequest;
use App\Http\Requests\Pet\StorePetRequest;
use App\Http\Requests\Pet\UpdatePetRequest;
use App\Http\Resources\Pet\PetCardResource;
use App\Http\Resources\Pet\PetDetailResource;
use App\Models\Pet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Listings, for the mobile app.
 *
 * The same Form Requests, Actions and Resources as Web\HomeController and
 * Web\PetController; what differs is only the response: a paginated
 * `{data, links, meta}` feed instead of an Inertia scroll prop, the detail
 * resource straight back from a write instead of a redirect, and a JSON body
 * from each toggle so the screen can update without a re-fetch.
 *
 * `index` and `show` are public and still authorize (PetPolicy::viewAny /
 * ::view take a nullable user). `show` records a view, the one GET that
 * writes, deduplicated per visitor by RecordPetView; the visitor key for a
 * guest is the IP, for a token bearer the account — identical to the web.
 *
 * `update` accepts multipart (photos), so the client posts it with
 * `_method=PUT`; PUT is a full replacement, not a patch (.ai/rules/resources.md).
 */
class PetController extends Controller
{
    use ResolvesRequestUser;

    public function index(ListHomeFeedRequest $request, ListHomeFeedPets $listHomeFeedPets): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Pet::class);

        return PetCardResource::collection($listHomeFeedPets->handle(
            viewer: $this->viewer($request),
            filters: $request->filters(),
            latitude: $request->latitude(),
            longitude: $request->longitude(),
            radiusKm: $request->radiusKm(),
        ));
    }

    public function show(
        Request $request,
        Pet $pet,
        LoadPetDetail $loadPetDetail,
        RecordPetView $recordPetView,
    ): PetDetailResource {
        $this->authorize('view', $pet);

        $recordPetView->handle($pet, $this->viewer($request), $this->visitorKey($request));

        return PetDetailResource::make($loadPetDetail->handle($pet, $this->viewer($request)));
    }

    public function store(StorePetRequest $request, CreatePet $createPet, LoadPetDetail $loadPetDetail): JsonResponse
    {
        $this->authorize('create', Pet::class);

        $pet = $createPet->handle(
            owner: $this->actor($request),
            data: $request->validated(),
            featuredImage: $request->featuredImage(),
            galleryImages: $request->galleryImages(),
        );

        return PetDetailResource::make($loadPetDetail->handle($pet, $this->actor($request)))
            ->response()
            ->setStatusCode(201);
    }

    public function update(
        UpdatePetRequest $request,
        Pet $pet,
        UpdatePet $updatePet,
        LoadPetDetail $loadPetDetail,
    ): PetDetailResource {
        $this->authorize('update', $pet);

        $updated = $updatePet->handle(
            pet: $pet,
            data: $request->validated(),
            featuredImage: $request->featuredImage(),
            galleryImages: $request->galleryImages(),
            deletedMediaIds: $request->deletedMediaIds(),
        );

        return PetDetailResource::make($loadPetDetail->handle($updated, $this->actor($request)));
    }

    public function destroy(Pet $pet, DeletePet $deletePet): Response
    {
        $this->authorize('delete', $pet);

        $deletePet->handle($pet);

        return response()->noContent();
    }

    public function toggleStatus(Pet $pet, TogglePetStatus $togglePetStatus): JsonResponse
    {
        $this->authorize('update', $pet);

        $status = $togglePetStatus->handle($pet);

        return response()->json([
            'status' => $status->value,
            'label' => $status->label(),
        ]);
    }

    public function toggleLike(
        Request $request,
        Pet $pet,
        ToggleLike $toggleLike,
        CountLikes $countLikes,
    ): JsonResponse {
        $this->authorize('like', $pet);

        $liked = $toggleLike->handle($pet, $this->actor($request));

        return response()->json([
            'is_liked' => $liked,
            'likes_count' => $countLikes->handle($pet),
        ]);
    }

    /**
     * Who is looking, for RecordPetView's dedup window — the same key shape
     * Web\PetController uses so a visitor who reads a listing in the browser
     * and then in the app is still one view.
     */
    private function visitorKey(Request $request): string
    {
        $user = $this->viewer($request);

        if ($user !== null) {
            return 'user:'.$user->getKey();
        }

        return 'guest:'.hash('xxh128', (string) $request->ip());
    }
}
