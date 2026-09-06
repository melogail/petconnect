<?php

namespace App\Http\Controllers\Settings;

use App\Actions\Profiles\DeleteUserAccount;
use App\Actions\Profiles\UpdateProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\DeleteProfileRequest;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Http\Resources\Profile\ProfileFormResource;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The account holder's own profile form.
 *
 * This is the *only* profile write screen. Web\ProfileController serves the
 * public page at `profile/{user}` and nothing else; there is deliberately no
 * `profile/{user}/edit` beside it, because two routes writing the same model is
 * what left the legacy app with `profile.update` answering both PUT and POST at
 * the URI that also served the read.
 *
 * Every action here acts on `$request->user()` — the target is never named in
 * the URL, so there is no "somebody else's account" case to get wrong. It still
 * calls `$this->authorize()`: UserPolicy exists now, `update` and `delete` are
 * genuine decisions with a subject, and .ai/rules/controllers.md only exempts
 * actions with no policy-governed model at all.
 *
 * ## What changed here in this phase
 *
 * `update` used to be `$user->fill($request->validated())` plus an inline
 * `email_verified_at` reset, which is fine for a form with two fields and
 * cannot express an avatar upload, a password change or a language switch. It
 * now delegates to Actions\Profiles\UpdateProfile, whose pipeline uploads the
 * new avatar **before** clearing the old one — the legacy ProfileImageService
 * did it the other way round, so a failed upload destroyed the existing photo
 * with nothing to restore.
 *
 * `destroy` used to be a bare `$user->delete()`. It was already confirmed by a
 * password and already invalidated the session — both of which the legacy
 * controller lacked entirely — but the delete itself stranded every
 * polymorphic row the database cascade could not reach: reviews written *about*
 * the account, the reports filed against those reviews and against its
 * comments, likes on its profile, comments and listings, saves of its listings,
 * its listings' media files, and its own notifications. It now runs
 * Actions\Profiles\DeleteUserAccount, which clears all of them inside one
 * transaction. See .ai/rules/actions.md.
 */
class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     *
     * `media` is eager loaded because ProfileFormResource reads the avatar with
     * getFirstMediaUrl(). A single model would not trip preventLazyLoading —
     * the guard is off on result sets of one row (.ai/rules/app.md) — so the
     * miss would have been a silent extra query rather than an exception.
     *
     * **The `profile` and `locales` props have no consumer yet.**
     * `resources/js/pages/settings/Profile.vue` was not rewritten alongside
     * them — it still renders `name` and `email` and reads both from
     * `page.props.auth.user`. The page is Phase 4's, together with
     * `profile/Show.vue` and the `messaging/Index.vue` / `messaging/Show.vue`
     * pair outstanding since Phase 2d. Serving the props now is deliberate: the
     * backend contract is settled and tested, and the form can be built against
     * it without another round trip through here. Nothing breaks in the
     * meantime, because the save is a PATCH and every new rule is nullable.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();

        $this->authorize('update', $user);

        return Inertia::render('settings/Profile', [
            'profile' => ProfileFormResource::make($user->loadMissing('media')),
            'locales' => $this->localeOptions(),
            'mustVerifyEmail' => $user instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     *
     * PATCH, and a PATCH in the real sense: only the keys the request sent are
     * written. That is the opposite of `pets.update`, which is a full
     * replacement — see App\Concerns\ProfileValidationRules for why the two
     * forms differ and what the trade costs.
     *
     * The request carries a file, so the client posts it as multipart with
     * `_method=PATCH`. Nothing here has to know that; the accessors on the Form
     * Request hand over the attribute bag and the upload as separate arguments,
     * so the controller never touches the bag.
     *
     * **It no longer changes a password.** The form used to accept
     * `current_password` / `password` beside the bio and the avatar while
     * `settings/Security` posted the same change to Fortify's
     * `user-password.update` — one outcome, two endpoints, two error
     * vocabularies. Fortify's is now the only path; see
     * App\Concerns\ProfileValidationRules.
     */
    public function update(UpdateProfileRequest $request, UpdateProfile $updateProfile): RedirectResponse
    {
        $user = $request->user();

        $this->authorize('update', $user);

        $updateProfile->handle(
            user: $user,
            attributes: $request->profileAttributes(),
            image: $request->uploadedImage(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return to_route('profile.edit');
    }

    /**
     * Delete the user's account.
     *
     * Three controls, in this order, none of which the legacy destroy had:
     *
     * 1. UserPolicy::delete — the acting user must be the subject.
     * 2. DeleteProfileRequest — `current_password`, so a borrowed session or a
     *    cross-site post cannot trigger it.
     * 3. Logout, session invalidation and CSRF token regeneration, so nothing
     *    of the session survives the row it belonged to.
     *
     * The logout happens *before* the delete on purpose: the session guard
     * holds the user instance, and ending the session first means no later
     * middleware on this response can resolve a user whose row is gone.
     */
    public function destroy(DeleteProfileRequest $request, DeleteUserAccount $deleteUserAccount): RedirectResponse
    {
        $user = $request->user();

        $this->authorize('delete', $user);

        Auth::logout();

        $deleteUserAccount->handle($user);

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    /**
     * The language choices the form's picker renders.
     *
     * Value and label only, matching what App\Concerns\HasOptions emits for
     * every enum-backed select in the application, so the frontend has one
     * shape for all of them. Locales are not an enum — they are a config list,
     * because adding one is a `lang/{code}` directory rather than a code change
     * — so the shape is built here rather than inherited.
     *
     * The label is the language's own endonym rather than a translated name:
     * somebody looking for Arabic is looking for "العربية", not for whatever
     * the interface currently calls it.
     *
     * @return list<array{value: string, label: string}>
     */
    protected function localeOptions(): array
    {
        /** @var list<string> $supported */
        $supported = config('petconnect.locales.supported', ['en']);

        return array_values(array_map(
            fn (string $locale): array => [
                'value' => $locale,
                'label' => __("locales.{$locale}"),
            ],
            $supported,
        ));
    }
}
