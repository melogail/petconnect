<?php

namespace App\Enums;

use App\Models\Pet;
use Illuminate\Database\Eloquent\Model;

enum Commentable: string
{
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

    public function findOrFail(int $id): Model
    {
        $modelClass = $this->modelClass();

        return $modelClass::query()->findOrFail($id);
    }
}
