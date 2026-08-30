<?php

namespace App\Enums;

use App\Concerns\ResolvesMorphTarget;
use App\Models\Pet;
use Illuminate\Database\Eloquent\Model;

/**
 * Whitelist of the models that may be commented on.
 *
 * Backing values mirror the morph map aliases registered in AppServiceProvider.
 */
enum Commentable: string
{
    use ResolvesMorphTarget;

    case Pet = 'pet';

    /**
     * @return class-string<Model>
     */
    public function modelClass(): string
    {
        return match ($this) {
            self::Pet => Pet::class,
        };
    }
}
