<?php

namespace App\Http\Controllers\Web;

use App\Actions\Pets\ListHomeFeedPets;
use App\Actions\Pets\ListPetCategories;
use App\Enums\ListingType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pet\ListHomeFeedRequest;
use App\Http\Resources\Pet\PetCardResource;
use App\Http\Resources\Pet\PetCategoryOptionResource;
use App\Models\Pet;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The public discovery feed.
 *
 * Open to guests: the feed is how somebody finds the app in the first place,
 * and the card payload carries nothing an owner would not print on a poster.
 * PetPolicy::viewAny is still consulted, because that is the one place the
 * feed's audience is decided and a guest-visible feed should be a recorded
 * decision rather than the absence of a check.
 *
 * `pets` is a scroll prop, not a plain one. The feed appends: a partial reload
 * for page 2 has to merge into the list already on the page, and a plain prop
 * would replace it, leaving the visitor looking at page 2 alone.
 * Inertia::scroll() labels the paginator's `data` array for merging and ships
 * the cursor metadata the <InfiniteScroll> component reads, so the frontend
 * wires up to `pets` and gets append-on-scroll without a bespoke handler. Every
 * other prop on this page is a plain replace-on-reload prop.
 */
class HomeController extends Controller
{
    public function index(
        ListHomeFeedRequest $request,
        ListHomeFeedPets $listHomeFeedPets,
        ListPetCategories $listPetCategories,
    ): Response {
        $this->authorize('viewAny', Pet::class);

        return Inertia::render('Home', [
            'pets' => Inertia::scroll(fn () => PetCardResource::collection($listHomeFeedPets->handle(
                viewer: $request->user(),
                filters: $request->filters(),
                latitude: $request->latitude(),
                longitude: $request->longitude(),
                radiusKm: $request->radiusKm(),
            ))),
            'filters' => $request->filters(),
            'nearby' => $request->hasCoordinates(),
            'radius' => $request->radiusKm(),
            'listingTypes' => ListingType::options(),
            'filterBounds' => [
                'default_radius_km' => $request->defaultRadiusKm(),
                'min_radius_km' => $request->minRadiusKm(),
                'max_radius_km' => $request->maxRadiusKm(),
                'max_age_years' => $request->maxAgeYears(),
                'default_age_min' => $request->defaultAgeMin(),
                'default_age_max' => $request->defaultAgeMax(),
            ],
            'categories' => Inertia::optional(
                fn () => PetCategoryOptionResource::collection($listPetCategories->handle()),
            ),
        ]);
    }
}
