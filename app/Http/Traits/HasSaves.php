<?php

namespace App\Http\Traits;

use App\Models\Save;

trait HasSaves
{
    public function saves()
    {
        return $this->morphMany(Save::class, 'saveable');
    }
}
