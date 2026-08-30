<?php

namespace App\Enums;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Whitelist of the models that may be reviewed.
 *
 * Backing values mirror the morph map aliases registered in AppServiceProvider.
 */
enum Reviewable: string
{
    case User = 'user';

    /**
     * @return class-string<Model>
     */
    public function modelClass(): string
    {
        return match ($this) {
            self::User => User::class,
        };
    }

    public function findOrFail(int $id): Model
    {
        $modelClass = $this->modelClass();

        return $modelClass::query()->findOrFail($id);
    }
}
