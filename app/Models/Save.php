<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Save extends Model
{
    protected $guarded = [];

    protected $casts = [
        'pet_id' => 'integer',
    ];

    public function pet()
    {
        return $this->belongsTo(Pet::class);
    }
}
