<?php

namespace App\Services;

use Illuminate\Support\Str;
use App\Models\User;

class ProfileImageService
{
    public function update($request, User $user)
    {
        if ($request->hasFile('profile_image')) {
            $file = $request->file('profile_image');
            $extension = $file->extension();
            $filename = Str::ulid() . '.' . $extension;

            // Remove the profile image if it exists
            $user->clearMediaCollection('users');

            // Upload the new profile image
            $user->addMediaFromRequest('profile_image')
                ->usingFileName($filename)
                ->withCustomProperties([
                    'profile_image' => true,
                ])
                ->toMediaCollection('users');
        }
    }
}
