<?php

namespace App\Http\Controllers\Web;

use App\Actions\UpdateUserProfileAction;
use App\Enums\ReportReason;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\ProfileResource;
use App\Models\User;

class ProfileController extends Controller
{
    public function __construct(
        protected UpdateUserProfileAction $updateUserProfileAction,
    ) {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(User $user)
    {
        return inertia('profile/Show', [
            'user' => ProfileResource::make($user->load([
                'pets',
                'reviews' => fn ($query) => $query
                    ->with('user')
                    ->withReportedByCurrentUser(),
            ])),
            'reportReasons' => ReportReason::options(),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(User $user)
    {
        $this->authorize('update', $user);

        return inertia('profile/Edit', [
            'user' => ProfileResource::make($user->load('media')),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProfileRequest $request, User $user)
    {
        $this->authorize('update', $user);

        $this->updateUserProfileAction->execute($request, $user);

        return to_route('profile.show', $user)->with('success', 'Profile updated successfully');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        $this->authorize('delete', $user);

        $user->delete();
        auth()->logout();

        // TODO::Send an email to verify the account deletion.

        return to_route('home', $user)->with('success', 'Profile deleted successfully');
    }
}
