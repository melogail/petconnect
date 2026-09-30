<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Pets\ListPetCategories;
use App\Http\Controllers\Controller;
use App\Http\Resources\Pet\PetCategoryOptionResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Every category with its breeds — the filter sheet and the listing form
 * both draw from it. The web ships this as a deferred Home prop.
 */
class CategoryController extends Controller
{
    public function index(ListPetCategories $listPetCategories): AnonymousResourceCollection
    {
        return PetCategoryOptionResource::collection($listPetCategories->handle());
    }
}
