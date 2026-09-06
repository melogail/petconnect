<?php

namespace App\Http\Controllers\Web;

use App\Actions\Likes\ToggleLike;
use App\Actions\Pets\CreatePet;
use App\Actions\Pets\DeletePet;
use App\Actions\Pets\ListPetCategories;
use App\Actions\Pets\LoadPetDetail;
use App\Actions\Pets\RecordPetView;
use App\Actions\Pets\TogglePetStatus;
use App\Actions\Pets\UpdatePet;
use App\Concerns\CommentValidationRules;
use App\Concerns\PetPhotoRules;
use App\Enums\HealthStatus;
use App\Enums\ListingType;
use App\Enums\PetGender;
use App\Enums\PetStatus;
use App\Enums\ReportCategory;
use App\Enums\ReportReason;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pet\StorePetRequest;
use App\Http\Requests\Pet\UpdatePetRequest;
use App\Http\Resources\Pet\PetCategoryOptionResource;
use App\Http\Resources\Pet\PetDetailResource;
use App\Models\Pet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pet listings.
 *
 * Every action authorizes through PetPolicy and then hands the work to one
 * Action or pipeline; no query or business rule lives here.
 *
 * ## Why a validation Concern is used by a controller
 *
 * `show` ships `commentBounds`, because this page hosts the comment composer
 * and the composer cannot enforce, or count towards, a ceiling it has not been
 * told. Both keys are read through App\Concerns\CommentValidationRules — the
 * same accessors the store and update requests build their `max:` rule from and
 * Actions\Comments\ListCommentThread paginates by — rather than from `config()`
 * here, so `petconnect.comments.max_length` and `.thread_per_page` each have
 * one spelling and one default, and neither the counter nor the thread's page
 * cursor can drift from the server. Web\ProfileController and
 * Web\ConversationController do the same for review and message bounds.
 *
 * `thread_per_page` is on that prop because the client genuinely cannot work it
 * out: the first slice of roots rides `pet.comments` and is sized by
 * `petconnect.pets.detail_comment_page_size`, while `comments.index` pages by
 * `petconnect.comments.thread_per_page`, and the two are independent env vars.
 * Assuming they matched made the first "load more" refetch the slice already on
 * screen. Its companion is `pet.root_comments_count`, which is the total the
 * shipped roots have to be compared against — `comments_count` counts replies
 * too. See Http\Resources\Pet\PetDetailResource.
 *
 * `create` and `edit` ship `photoBounds` for the same reason and read it the
 * same way, through App\Concerns\PetPhotoRules. The photo step caps the gallery
 * and compresses each file to fit the per-image ceiling before it is attached,
 * and both numbers were hardcoded in the Vue pages against
 * `config/petconnect.php`. It is PetPhotoRules rather than PetValidationRules
 * deliberately: that trait carries `featuredImage()` / `galleryImages()`, which
 * call `Illuminate\Http\Request::file()` and would fatal on a controller, and
 * rule methods that read `$this->input()`. PetPhotoRules is the half that is
 * safe off a Form Request — App\Nova\Pet already uses it for that reason.
 */
class PetController extends Controller
{
    use CommentValidationRules;
    use PetPhotoRules;

    /**
     * Show the form for publishing a listing.
     */
    public function create(ListPetCategories $listPetCategories): Response
    {
        $this->authorize('create', Pet::class);

        return Inertia::render('pets/Create', [
            'categories' => PetCategoryOptionResource::collection($listPetCategories->handle()),
            'listingTypes' => ListingType::options(),
            'statuses' => PetStatus::options(),
            'genders' => PetGender::options(),
            'healthStatuses' => HealthStatus::options(),
            'photoBounds' => $this->photoBounds(),
        ]);
    }

    /**
     * Publish a listing.
     */
    public function store(StorePetRequest $request, CreatePet $createPet): RedirectResponse
    {
        $this->authorize('create', Pet::class);

        $pet = $createPet->handle(
            owner: $request->user(),
            data: $request->validated(),
            featuredImage: $request->featuredImage(),
            galleryImages: $request->galleryImages(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Listing published.')]);

        return to_route('pets.show', $pet);
    }

    /**
     * Show a listing. Public: guests reach a shared link without signing in.
     *
     * The report vocabulary ships as props because this page hosts the *comment*
     * report dialog, and ReportCategory / ReportReason had no route anywhere in
     * the application — the report vertical shipped a write endpoint whose
     * dialog had no source for its two select controls. Same arrangement
     * `create` uses for the listing form's enums, and the same two props
     * `profile.show` carries for the review report dialog.
     */
    public function show(
        Request $request,
        Pet $pet,
        LoadPetDetail $loadPetDetail,
        RecordPetView $recordPetView,
    ): Response {
        $this->authorize('view', $pet);

        $recordPetView->handle($pet, $request->user(), $this->visitorKey($request));

        return Inertia::render('pets/Show', [
            'pet' => PetDetailResource::make($loadPetDetail->handle($pet, $request->user())),
            'reportCategories' => ReportCategory::options(),
            'reportReasons' => ReportReason::options(),
            'commentBounds' => $this->commentBounds(),
        ]);
    }

    /**
     * Show the form for editing a listing.
     *
     * The ownership check is the one the legacy edit action was missing: it
     * rendered the full record — veterinarian contact details, exact
     * coordinates, medications — for any verified account that knew the id.
     */
    public function edit(
        Request $request,
        Pet $pet,
        LoadPetDetail $loadPetDetail,
        ListPetCategories $listPetCategories,
    ): Response {
        $this->authorize('update', $pet);

        return Inertia::render('pets/Edit', [
            'pet' => PetDetailResource::make($loadPetDetail->handle($pet, $request->user())),
            'categories' => PetCategoryOptionResource::collection($listPetCategories->handle()),
            'listingTypes' => ListingType::options(),
            'statuses' => PetStatus::options(),
            'genders' => PetGender::options(),
            'healthStatuses' => HealthStatus::options(),
            'photoBounds' => $this->photoBounds(),
        ]);
    }

    /**
     * Apply an edit to a listing.
     */
    public function update(UpdatePetRequest $request, Pet $pet, UpdatePet $updatePet): RedirectResponse
    {
        $this->authorize('update', $pet);

        $updatePet->handle(
            pet: $pet,
            data: $request->validated(),
            featuredImage: $request->featuredImage(),
            galleryImages: $request->galleryImages(),
            deletedMediaIds: $request->deletedMediaIds(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Listing updated.')]);

        return to_route('pets.show', $pet);
    }

    /**
     * Retire a listing.
     */
    public function destroy(Pet $pet, DeletePet $deletePet): RedirectResponse
    {
        $this->authorize('delete', $pet);

        $deletePet->handle($pet);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Listing removed.')]);

        return to_route('home');
    }

    /**
     * Flip a listing between available and unavailable.
     */
    public function toggleStatus(Pet $pet, TogglePetStatus $togglePetStatus): RedirectResponse
    {
        $this->authorize('update', $pet);

        $status = $togglePetStatus->handle($pet);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Listing marked as :status.', ['status' => $status->label()]),
        ]);

        return back();
    }

    /**
     * Toggle the viewer's like on a listing.
     *
     * Actions\Likes\ToggleLike, not a pet-specific Action: comments are
     * likeable too, and a second one-line wrapper is where a shared flow starts
     * drifting per model. It replaced Actions\Pets\TogglePetLike.
     */
    public function toggleLike(Request $request, Pet $pet, ToggleLike $toggleLike): RedirectResponse
    {
        $this->authorize('like', $pet);

        $toggleLike->handle($pet, $request->user());

        return back();
    }

    /**
     * The key a view is deduplicated against.
     *
     * A signed-in visitor is keyed by id, so the dedup follows them across
     * devices. A guest is keyed by their IP address — **not** by the session
     * id, which is what this used to do.
     *
     * The session id looked like the privacy-preserving choice and was in fact
     * the inflatable one: it comes from a cookie the client controls, so a
     * caller that keeps no cookie jar draws a brand new session, and therefore
     * a brand new dedup key, on every single request. `curl` in a loop against
     * a public, unthrottled GET incremented `views` once per request with no
     * window applying at all. Whatever the counter is worth, it should not be
     * that easy to write to.
     *
     * The IP is hashed rather than stored: the cache key needs to be stable per
     * visitor, not readable, and `pet-view:{id}:{key}` rows would otherwise be a
     * log of who read which listing. xxh128 is a non-cryptographic hash chosen
     * for speed — this is a cache key, not a credential — and the addresses it
     * covers are guessable by construction, so it is obfuscation, not secrecy.
     *
     * The cost, paid knowingly: everyone behind one NAT now shares a key, so a
     * household or an office counts once per window. That is the right way for
     * a vanity number to be wrong — it undercounts honest traffic rather than
     * overcounting a script. A caller with a pool of addresses can still
     * inflate it, which is why RecordPetView's docblock says not to build
     * anything on `views`.
     */
    private function visitorKey(Request $request): string
    {
        $user = $request->user();

        if ($user !== null) {
            return 'user:'.$user->getKey();
        }

        return 'guest:'.hash('xxh128', (string) $request->ip());
    }
}
