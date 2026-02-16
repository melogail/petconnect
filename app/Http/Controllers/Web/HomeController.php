<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Resources\Pet\PetCardResource;
use App\Models\Pet;

class HomeController extends Controller
{
    public function index()
    {
        $pets = PetCardResource::collection(Pet::all());

        return inertia('Home', [
            'pets' => $pets
        ]);
    }
}
