<?php

namespace App\Observers;

use App\Enums\PetStatus;
use App\Models\Pet;
use Illuminate\Support\Str;

class PetObserver
{
    /**
     * Give every listing a URL slug before its row exists.
     *
     * The slug is the breed's English name, or the category's when the
     * listing names no breed, followed by a random suffix: the prefix is for
     * humans reading the URL, the suffix is what makes it unique. Uniqueness
     * itself is the `pets.slug` unique index's guarantee, as with
     * `UserObserver`'s media directory name. A slug supplied explicitly (by a
     * seeder, or a factory state) is kept.
     */
    public function creating(Pet $pet): void
    {
        if (filled($pet->slug)) {
            return;
        }

        $prefix    = Str::slug($pet->breed->name ?? $pet->category->name ?? '');

        $pet->slug = ($prefix === '' ? 'pet' : $prefix).'-'.Str::lower(Str::random(8));
    }

    public function updated(Pet $pet): void
    {
        if ($pet->quantity > 1 && $pet->male_count === 0 && $pet->female_count === 0) {
            $pet->status = PetStatus::Unavailable;
            $pet->saveQuietly();
        }
    }
}
