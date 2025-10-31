<?php

namespace App\Http\Repository;

class PetRepository
{
    public function getAll()
    {
        return Pet::all();
    }
}
