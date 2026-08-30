<?php

namespace App\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Shared resolution behaviour for the polymorphic target whitelist enums
 * (`Commentable`, `Reportable`, `Reviewable`).
 *
 * The using enum maps each case to a concrete model class; this trait turns
 * that mapping into a safe lookup so no raw class name ever reaches Eloquent.
 */
trait ResolvesMorphTarget
{
    /**
     * The model class this case resolves to.
     *
     * @return class-string<Model>
     */
    abstract public function modelClass(): string;

    /**
     * Resolve the whitelisted morph target by its primary key.
     *
     * @throws ModelNotFoundException<Model>
     */
    public function findOrFail(int $id): Model
    {
        $modelClass = $this->modelClass();

        return $modelClass::query()->findOrFail($id);
    }
}
