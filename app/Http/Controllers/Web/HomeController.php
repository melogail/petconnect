<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Resources\Pet\PetCardResource;
use App\Models\Pet;

class HomeController extends Controller
{
    public function index()
    {
        $pets = PetCardResource::collection(
            Pet::with(['category', 'breed', 'user'])
                ->withCount(['likes', 'comments'])
                ->orderBy('created_at', 'desc')
                ->paginate(12)
        );

        if (request()->wantsJson()) {
            return $pets;
        }

        return inertia('Home', [
            'pets' => $pets,
        ]);
    }
}
