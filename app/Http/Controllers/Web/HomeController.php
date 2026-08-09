<?php

namespace App\Http\Controllers\Web;

use App\Enums\ListingType;
use App\Enums\ReportReason;
use App\Http\Controllers\Controller;
use App\Http\Requests\ListHomePetsRequest;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\Pet\PetCardResource;
use App\Models\Category;
use App\Services\PetService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Response;

class HomeController extends Controller
{
    public function __construct(protected PetService $petService) {}

    public function index(ListHomePetsRequest $request): Response|AnonymousResourceCollection
    {
        $filters = $request->filters();

        $pets = PetCardResource::collection(
            $this->petService->getHomePets(
                latitude: $request->latitude(),
                longitude: $request->longitude(),
                radiusKm: $request->hasCoordinates() ? $request->radiusKm() : null,
                filters: $filters,
            )
        );

        if ($request->wantsJson()) {
            return $pets;
        }

        $categories = Category::query()
            ->with(['breeds' => fn ($query) => $query->orderBy('name')])
            ->orderBy('name')
            ->get();

        return inertia('Home', [
            'pets' => $pets,
            'reportReasons' => ReportReason::options(),
            'nearby' => $request->hasCoordinates(),
            'radius' => $request->hasCoordinates() ? $request->radiusKm() : null,
            'defaultRadius' => $request->defaultRadiusKm(),
            'maxRadius' => $request->maxRadiusKm(),
            'categories' => CategoryResource::collection($categories),
            'listingTypes' => ListingType::options(),
            'filters' => $filters,
            'filterDefaults' => [
                'age_min' => (float) config('petconnect.filters.default_age_min', 0),
                'age_max' => (float) config('petconnect.filters.default_age_max', 15),
                'max_age' => $request->maxAgeYears(),
            ],
        ]);
    }
}
