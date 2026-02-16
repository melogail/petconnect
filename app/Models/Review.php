<?php

namespace App\Models;

use App\Traits\HasReport;
use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    use HasReport;

    protected $guarded = [];

    /**
     * ======================
     * == RELATIONSHIPS
     * ======================
     */

    public function reviewable()
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
