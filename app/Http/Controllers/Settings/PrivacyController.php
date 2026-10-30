<?php

namespace App\Http\Controllers\Settings;

use App\Concerns\ResolvesRequestUser;
use App\Http\Controllers\Controller;
use App\Http\Resources\Profile\ProfileFormResource;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The privacy screen of the settings area.
 *
 * Read-only: the two flags it renders are saved through `profile.update`,
 * the same PATCH every other settings panel posts to, which answers `back()`
 * so the member lands here again.
 */
class PrivacyController extends Controller
{
    use ResolvesRequestUser;

    public function edit(Request $request): Response
    {
        $user = $this->actor($request);

        $this->authorize('update', $user);

        return Inertia::render('settings/Privacy', [
            'profile' => ProfileFormResource::make($user->loadMissing('media')),
        ]);
    }
}
