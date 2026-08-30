<?php

namespace App\Enums;

use App\Models\Comment;
use App\Models\Review;
use Illuminate\Database\Eloquent\Model;

/**
 * Whitelist of the models that may be reported.
 *
 * Backing values mirror the morph map aliases registered in AppServiceProvider.
 */
enum Reportable: string
{
    case Comment = 'comment';
    case Review = 'review';

    /**
     * @return class-string<Model>
     */
    public function modelClass(): string
    {
        return match ($this) {
            self::Comment => Comment::class,
            self::Review => Review::class,
        };
    }

    public function findOrFail(int $id): Model
    {
        $modelClass = $this->modelClass();

        return $modelClass::query()->findOrFail($id);
    }
}
