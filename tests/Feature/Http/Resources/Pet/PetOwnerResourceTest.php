<?php

use App\Http\Resources\Pet\PetOwnerResource;
use App\Models\User;
use Illuminate\Http\Request;

function viewerRequest(?User $viewer): Request
{
    $request = Request::create('/');
    $request->setUserResolver(fn (): ?User => $viewer);

    return $request;
}

test('ships a wa.me link built from the phone digits to a signed-in viewer when the owner opted in', function () {
    $owner = User::factory()->create(['phone' => '+20 (100) 000-0000', 'whatsapp_enabled' => true]);

    expect(PetOwnerResource::make($owner)->toArray(viewerRequest(User::factory()->create())))
        ->toMatchArray(['whatsapp' => 'https://wa.me/201000000000']);
});

test('ships null to a guest even when the owner opted in', function () {
    $owner = User::factory()->create(['phone' => '+20-100-000-0000', 'whatsapp_enabled' => true]);

    expect(PetOwnerResource::make($owner)->toArray(viewerRequest(null))['whatsapp'])->toBeNull();
});

test('ships null when the owner has not opted in or has no phone', function () {
    $optedOut = User::factory()->create(['phone' => '+20-100-000-0000']);
    $noPhone  = User::factory()->create(['phone' => null, 'whatsapp_enabled' => true]);

    $viewer   = viewerRequest(User::factory()->create());

    expect(PetOwnerResource::make($optedOut)->toArray($viewer)['whatsapp'])->toBeNull()
        ->and(PetOwnerResource::make($noPhone)->toArray($viewer)['whatsapp'])->toBeNull();
});
