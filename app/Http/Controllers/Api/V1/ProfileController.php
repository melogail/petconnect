<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Likes\CountLikes;
use App\Actions\Likes\ToggleLike;
use App\Actions\Profiles\LoadProfileForDisplay;
use App\Concerns\ResolvesRequestUser;
use App\Http\Controllers\Controller;
use App\Http\Resources\Pet\PetCardResource;
use App\Http\Resources\Profile\ProfileResource;
use App\Http\Resources\Review\ReviewResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * A public profile, in three requests instead of one page.
 *
 * Web\ProfileController ships the summary, the listings page and the reviews
 * page as three props of one Inertia response. A JSON client cannot receive
 * two independent paginators inside one object without losing their `meta`
 * (a nested resource collection serialises to its items only), so the three
 * halves of LoadProfileForDisplay are exposed as three routes and each keeps
 * its own `{data, links, meta}` envelope. Every one authorizes `view` against
 * UserPolicy, whose `view` takes a nullable user; a deactivated account is a
 * 404 before any of them run (User::resolveRouteBinding).
 */
class ProfileController extends Controller
{
    use ResolvesRequestUser;

    public function show(Request $request, User $user, LoadProfileForDisplay $loadProfileForDisplay): ProfileResource
    {
        $this->authorize('view', $user);

        return ProfileResource::make($loadProfileForDisplay->summary($user, $this->viewer($request)));
    }

    public function pets(Request $request, User $user, LoadProfileForDisplay $loadProfileForDisplay): AnonymousResourceCollection
    {
        $this->authorize('view', $user);

        return PetCardResource::collection($loadProfileForDisplay->listings($user, $this->viewer($request)));
    }

    public function reviews(Request $request, User $user, LoadProfileForDisplay $loadProfileForDisplay): AnonymousResourceCollection
    {
        $this->authorize('view', $user);

        return ReviewResource::collection($loadProfileForDisplay->reviews($user, $this->viewer($request)));
    }

    public function toggleLike(
        Request $request,
        User $user,
        ToggleLike $toggleLike,
        CountLikes $countLikes,
    ): JsonResponse {
        $this->authorize('like', $user);

        $liked = $toggleLike->handle($user, $this->actor($request));

        return response()->json([
            'is_liked' => $liked,
            'likes_count' => $countLikes->handle($user),
        ]);
    }
}
