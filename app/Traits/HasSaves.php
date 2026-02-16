<?php

namespace App\Traits;

use App\Models\Save;

trait HasSaves
{
    public function saves()
    {
        return $this->morphMany(Save::class, 'saveable');
    }
}
