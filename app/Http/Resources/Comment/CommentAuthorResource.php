<?php

namespace App\Http\Resources\Comment;

use App\Http\Resources\User\UserSummaryResource;

/**
 * The author of a comment.
 *
 * The five keys and the reasoning behind them now live in
 * App\Http\Resources\User\UserSummaryResource, which this class and
 * Http\Resources\Pet\PetOwnerResource both extend. Both used to carry their own
 * copy of the same toArray(), each with a docblock saying the right fix was one
 * shared class — the messaging vertical needed the same shape a third time,
 * which is where that stopped being a deferrable cost.
 *
 * The subclass survives so a comment payload can name its author "the comment
 * author" without the Comments namespace importing a class called
 * PetOwnerResource to say so, and so a future author-only key (a badge, a
 * moderation flag) has somewhere to go that does not widen every user summary
 * in the app.
 *
 * The eager-loading contract is unchanged and is the parent's: whoever loads
 * the author must load `user.media`, because the avatar is a
 * getFirstMediaUrl() call. Every comment loader in the app does
 * (LoadPetDetail, EagerLoadFeedRelations, ListCommentThread,
 * ListCommentReplies, PersistComment).
 */
class CommentAuthorResource extends UserSummaryResource {}
