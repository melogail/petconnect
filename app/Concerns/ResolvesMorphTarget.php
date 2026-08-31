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

    /**
     * Resolve the whitelisted morph target the way a route binding would.
     *
     * Same contract as findOrFail() — the model, or ModelNotFoundException —
     * but it goes through the model's own `resolveRouteBinding()` instead of a
     * bare `find()`, so a model that has recorded extra conditions for being
     * addressed by a bare id gets them applied here too.
     *
     * That matters because a URL like `reports/comment/{id}` asks exactly the
     * question `comments/{comment}` asks: may this id be addressed directly,
     * right now, by whoever sent it? `Comment::resolveRouteBinding()` already
     * answers it — it refuses to bind a comment whose commentable is hidden, so
     * a soft-deleted listing's discussion cannot be read at a guessable
     * sequential id — and `Review::resolveRouteBinding()` refuses a review
     * whose target is gone. Re-deriving that per flow would leave each new
     * caller free to forget it, which is how the same class of bug shipped
     * twice already (.ai/rules/app.md, "A route-bound child model must
     * re-derive its parent's visibility"). Delegating means there is one
     * answer per model, in the place the model already keeps it.
     *
     * A model with no override falls through to Eloquent's default
     * `where(getRouteKeyName(), $value)->first()`, which is `find()` plus the
     * model's global scopes — identical to findOrFail() minus the throw. So
     * this is never *weaker* than findOrFail(); it is that plus whatever the
     * model itself adds.
     *
     * findOrFail() is kept for the flows that want the plain lookup:
     * `Commentable` is only ever resolved against Pet, whose soft-delete scope
     * is already the whole answer.
     *
     * @throws ModelNotFoundException<Model>
     */
    public function findVisibleOrFail(int $id): Model
    {
        $modelClass = $this->modelClass();

        $model = (new $modelClass)->resolveRouteBinding($id);

        if (! $model instanceof Model) {
            throw (new ModelNotFoundException)->setModel($modelClass, [$id]);
        }

        return $model;
    }
}
