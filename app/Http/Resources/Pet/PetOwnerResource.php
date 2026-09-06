<?php

namespace App\Http\Resources\Pet;

use App\Http\Resources\User\UserSummaryResource;

/**
 * The listing owner as a pet payload shows them.
 *
 * The five keys and the reasoning behind them now live in
 * App\Http\Resources\User\UserSummaryResource, which this class and
 * Http\Resources\Comment\CommentAuthorResource both extend. Both used to carry
 * their own copy of the same toArray(); the messaging vertical needed the same
 * shape a third time, which is where that stopped being a deferrable cost.
 *
 * The subclass survives so a pet payload can name its owner "the owner" without
 * the Pet namespace importing a class called CommentAuthorResource to say so,
 * and so a future owner-only key (response rate, verified-seller badge) has
 * somewhere to go that does not widen every user summary in the app.
 *
 * The eager-loading contract is unchanged and is the parent's: whoever loads
 * the User must eager load `user.media`, because the avatar is a
 * getFirstMediaUrl() call — measured at 48 silent queries on a 12-card feed
 * before the loaders carried it.
 */
class PetOwnerResource extends UserSummaryResource {}
