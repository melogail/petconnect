<?php

namespace App\Http\Controllers\Web;

use App\Actions\Pets\ListHomeFeedPets;
use App\Actions\Pets\ListPetCategories;
use App\Concerns\CommentValidationRules;
use App\Enums\ListingType;
use App\Enums\ReportCategory;
use App\Enums\ReportReason;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pet\ListHomeFeedRequest;
use App\Http\Resources\Pet\PetCardResource;
use App\Http\Resources\Pet\PetCategoryOptionResource;
use App\Models\Pet;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The public discovery feed.
 *
 * Open to guests: the feed is how somebody finds the app in the first place,
 * and the card payload carries nothing an owner would not print on a poster.
 * PetPolicy::viewAny is still consulted, because that is the one place the
 * feed's audience is decided and a guest-visible feed should be a recorded
 * decision rather than the absence of a check.
 *
 * `pets` is a scroll prop, not a plain one. The feed appends: a partial reload
 * for page 2 has to merge into the list already on the page, and a plain prop
 * would replace it, leaving the visitor looking at page 2 alone.
 * Inertia::scroll() labels the paginator's `data` array for merging and ships
 * the cursor metadata the <InfiniteScroll> component reads, so the frontend
 * wires up to `pets` and gets append-on-scroll without a bespoke handler.
 *
 * `categories` is deferred, not optional. Both skip the category tree on the
 * first render — it only opens with the filter sheet — but a deferred prop is
 * announced in `deferredProps`, so the client fetches it by itself and the page
 * needs no onMounted reload of its own. An optional prop is never announced and
 * only ever arrives if page code asks for it by name. Every other prop here is
 * a plain replace-on-reload prop.
 *
 * ## Why the feed carries the report vocabulary
 *
 * A feed card opens the same comments dialog the pet detail page does, and that
 * dialog hosts the *comment* report affordance. It reads `reportCategories` and
 * `reportReasons` off page props, so without them here the affordance mounted
 * from a card has nothing to render — quietly, because the Vue props are
 * optional and `undefined` is not an error. Comments are the only reportable
 * surface the feed has: App\Enums\Reportable is `comment|review`, and a listing
 * is neither.
 *
 * This is parity, not a new capability. The legacy `HomeController` shipped
 * `ReportReason::options()` and `Home.vue` forwarded it through `PetCard` into
 * the same dialog; categories were the half it was missing, which is the gap
 * Web\ProfileController's docblock records for the review dialog.
 *
 * Same key names and the same `{value, label}` shape PetController::show and
 * ProfileController::show emit, straight off App\Concerns\HasOptions, so one
 * dialog component consumes all three pages without asking which one mounted
 * it. Cost, measured with `strlen(json_encode(...))` over both option lists
 * including their prop keys: 540 bytes, uncompressed, on every feed response.
 *
 * ## And why it carries the comment ceiling
 *
 * `commentBounds` is the third prop of that class, and the last one the
 * dialog reads that only `pets.show` used to supply. The composer inside the
 * dialog cannot enforce, or count towards, a ceiling it has not been told:
 * opened from a card the textarea carried no `maxlength` and no counter, took
 * 1,200 characters, and the server then refused them at the `max:` rule with
 * the dialog still open and the text stranded. Opened from the detail page the
 * same component stops at the limit, because that page ships the prop.
 *
 * It is read through App\Concerns\CommentValidationRules, exactly as
 * PetController::show reads it — the same accessors the comment Form Requests
 * build their `max:` rule from and Actions\Comments\ListCommentThread pages
 * by — so the number is never restated here and the counter cannot drift from
 * the validator. Same key, same snake_case shape, so the dialog never branches
 * on which page mounted it. Two `config()` reads, no query.
 */
class HomeController extends Controller
{
    use CommentValidationRules;

    public function index(
        ListHomeFeedRequest $request,
        ListHomeFeedPets $listHomeFeedPets,
        ListPetCategories $listPetCategories,
    ): Response {
        $this->authorize('viewAny', Pet::class);

        return Inertia::render('Home', [
            'pets' => Inertia::scroll(fn () => PetCardResource::collection($listHomeFeedPets->handle(
                viewer: $request->user(),
                filters: $request->filters(),
                latitude: $request->latitude(),
                longitude: $request->longitude(),
                radiusKm: $request->radiusKm(),
            ))),
            'filters' => $request->filters(),
            'nearby' => $request->hasCoordinates(),
            'radius' => $request->radiusKm(),
            'listingTypes' => ListingType::options(),
            'reportCategories' => ReportCategory::options(),
            'reportReasons' => ReportReason::options(),
            'commentBounds' => $this->commentBounds(),
            'filterBounds' => [
                'default_radius_km' => $request->defaultRadiusKm(),
                'min_radius_km' => $request->minRadiusKm(),
                'max_radius_km' => $request->maxRadiusKm(),
                'max_age_years' => $request->maxAgeYears(),
                'default_age_min' => $request->defaultAgeMin(),
                'default_age_max' => $request->defaultAgeMax(),
            ],
            'categories' => Inertia::defer(
                fn () => PetCategoryOptionResource::collection($listPetCategories->handle()),
            ),
        ]);
    }
}
