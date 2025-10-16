<?php

namespace App\Models;

use App\Http\Traits\HasComments;
use App\Http\Traits\HasLikes;
use App\Http\Traits\HasSaves;
use Illuminate\Database\Eloquent\Model;

class Pet extends Model
{
    use HasComments, HasLikes, HasSaves;

    protected $guarded = [];

    protected $casts = [
        'category_id' => 'integer',
        'breed_id' => 'integer',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function breed()
    {
        return $this->belongsTo(Breed::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
