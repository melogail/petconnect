<?php

namespace App\Contracts;

use App\Models\Comment;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;

/**
 * A model that users are able to comment on.
 *
 * App\Concerns\HasComments supplies comments() and rootComments(); implementing
 * this interface is what makes the model a legal target of the publish flow and
 * opts it into comment notifications, exactly the way App\Contracts\Likeable
 * relates to App\Concerns\HasLikes. The two halves are deliberately separate:
 * the trait is the storage, the interface is the declaration that this model
 * really accepts a public thread and knows who to tell about it.
 *
 * App\Enums\Commentable is the *input* whitelist — it maps the string a request
 * may send to a model class. This interface is the *model-side* invariant.
 * Pipelines\Comments\PublishComment\RequireCommentThread checks the interface on
 * the write path and Actions\Comments\ListCommentThread on the read path, so
 * adding an enum case for a model that never opted in fails loudly instead of
 * writing comment rows onto something with no thread to read them back from.
 *
 * (Same-named, different namespace, on purpose: the enum names the wire value,
 * the contract names the capability. No file needs both — the enum stops at
 * ResolveCommentable and the contract starts at the context.)
 */
interface Commentable
{
    /**
     * @return MorphMany<Comment, static>
     */
    public function comments(): MorphMany;

    /**
     * The top-level comments only; replies hang off Comment::replies().
     *
     * Declared here so the read path can page a thread through the relation
     * instead of rebuilding `commentable_type` / `commentable_id` / `parent_id`
     * by hand — a hand-built morph filter is the one shape that silently
     * matches nothing when an alias changes. See .ai/rules/app.md.
     *
     * @return MorphMany<Comment, static>
     */
    public function rootComments(): MorphMany;

    /**
     * Users who should be notified when a top-level comment is posted here.
     *
     * A reply notifies the comment it answers, not the model, so this is only
     * consulted for a root comment. Returning an empty collection is a valid
     * answer and means "nobody is told".
     *
     * @return Collection<int, User>
     */
    public function commentNotificationRecipients(): Collection;
}
