<?php

namespace App\Actions;

use App\Models\User;
use App\Services\ProfileImageService;
use App\Support\LocaleManager;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;

class UpdateUserProfileAction
{
    public function __construct(protected ProfileImageService $profileImageService)
    {
        //
    }

    public function execute($request, User $user): void
    {
        $validated = Arr::except(
            $request->validated(),
            ['current_password', 'new_password', 'confirm_password', 'two_factor_enabled', 'profile_image']
        );

        if ($request->filled('new_password')) {
            $validated['password'] = Hash::make($request->new_password);
        }

        if ($request->hasFile('profile_image')) {
            $this->profileImageService->update($request, $user);
        }

        $user->update($validated);

        if (array_key_exists('locale', $validated) && filled($validated['locale'])) {
            LocaleManager::apply($validated['locale'], $user);
        }
    }
}
