<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProfileReqeuest;
use App\Models\User;
use Illuminate\Http\Request;

class ProfileController extends Controller
{

    /**
     * Display the specified resource.
     */
    public function show(User $user)
    {
        return inertia('profile/Show', [
            'user' => $user
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(User $user)
    {
        inertia('profile/Edit', [
            'user' => $user
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProfileReqeuest $request, User $user)
    {
        $user->update($request->validated());
        return to_route('profile.show', $user)->with('success', 'Profile updated successfully');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        $user->delete();
        // destroy the session
        auth()->logout();

        // TODO::Send an email to verify the account deletion.

        return to_route('home', $user)->with('success', 'Profile deleted successfully');
    }
}
