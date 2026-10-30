<?php

namespace App\Providers;

use App\MediaLibrary\MediaPathGenerator;
use App\MediaLibrary\OwnerDirectoryResolver;
use App\MediaLibrary\TemporaryDirectoryCleaningFileManipulator;
use App\Models\Admin;
use App\Models\Breed;
use App\Models\Category;
use App\Models\Comment;
use App\Models\ContactMessage;
use App\Models\Pet;
use App\Models\Report;
use App\Models\Review;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Observers\MediaOwnerDirectoryObserver;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\LazyLoadingViolationException;
use Illuminate\Foundation\DevCommands;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Nova\Util;
use Spatie\MediaLibrary\Conversions\FileManipulator;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * OwnerDirectoryResolver is scoped rather than resolved fresh: it memoises
     * the owner lookup per morph pair, and a request-lifetime instance keeps
     * that memo from outliving a request or a refreshed test database.
     * MediaPathGenerator is scoped for the same reason it always was —
     * medialibrary calls app() on it once per generated path — and now takes
     * the resolver rather than owning the lookup.
     *
     * The FileManipulator override is deliberately NOT scoped, and the contrast
     * with those two is the whole justification: they are scoped because they
     * hold a memo that has to live exactly one request and no longer, and this
     * one holds no state at all — it wraps a `finally` around the package's own
     * method and keeps nothing between calls. Binding it `scoped` or `singleton`
     * would advertise shared state that does not exist, and would hold one
     * instance across every job a queue worker runs for no gain. A plain bind
     * covers the inline path, PerformConversionsJob and
     * `media-library:regenerate` alike, because the package resolves the
     * manipulator from the container in all three.
     */
    public function register(): void
    {
        $this->app->scoped(OwnerDirectoryResolver::class);
        $this->app->scoped(MediaPathGenerator::class);
        $this->app->bind(FileManipulator::class, TemporaryDirectoryCleaningFileManipulator::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Artisan dev command. Horizon and Reverb register their own panes.
        DevCommands::except('server', 'logs');

        $this->configureDefaults();
        $this->configureMorphMap();
        $this->configureMediaOwnership();
    }

    /**
     * Make every media row carry the directory its files are stored under.
     *
     * MediaPathGenerator builds `media/{owner directory}/...` and the owner
     * directory is a custom property on the row. The application's own upload
     * paths set it; nothing forced any *other* writer to, and the one that
     * mattered did not — Ebess\AdvancedNovaMediaLibrary's Media field fills
     * only the custom properties the field was configured with, which is
     * nothing for all four of our media fields, so every avatar and pet photo
     * an admin uploaded through Nova permanently lacked it and fell into the
     * generator's database fallback: two queries per owner per request, on the
     * public listing page.
     *
     * The observer closes it at the one point every writer passes through.
     * Configuring the four Nova fields with withCustomProperties() would have
     * fixed those four and nothing about the fifth uploader.
     */
    protected function configureMediaOwnership(): void
    {
        Media::observe(MediaOwnerDirectoryObserver::class);
    }

    /**
     * Map every morphable model to a short, stable alias.
     *
     * Polymorphic columns (likes, saves, comments, reviews, reports, media,
     * notifications) store these aliases instead of fully qualified class
     * names, so renaming or moving a model cannot orphan existing rows.
     *
     * The map is not only about the application's own polymorphic relations.
     * Because enforceMorphMap() is enforcing, every model Nova exposes as a
     * resource has to appear here too: Nova's ActionEvent writes
     * `actionable_type`, `target_type` and `model_type` as morph values, so the
     * first action run against an unmapped model throws
     * ClassMorphViolationException. `report` was the one missing entry when the
     * Report resource was added in Phase 3 — Report is the target of no
     * polymorphic relation in the application, only of Nova's action log. The
     * eight models below are exactly the eight Nova resources.
     */
    protected function configureMorphMap(): void
    {
        Relation::enforceMorphMap([
            'admin'           => Admin::class,
            'breed'           => Breed::class,
            'category'        => Category::class,
            'comment'         => Comment::class,
            'contact_message' => ContactMessage::class,
            'pet'             => Pet::class,
            'report'          => Report::class,
            'review'          => Review::class,
            'ticket'          => Ticket::class,
            'ticket_message'  => TicketMessage::class,
            'user'            => User::class,
        ]);
    }

    /**
     * Configure default behaviors for production-ready applications.
     *
     * JsonResource::withoutWrapping() is what keeps Inertia props readable: a
     * single resource arrives as the object itself rather than as {data: {...}},
     * while a paginated collection keeps its data/links/meta envelope, because
     * pagination metadata forces the wrapper back on regardless of this call.
     *
     * preventLazyLoading() is the guardrail that catches an N+1 while it is
     * being written rather than in production logs. It is on everywhere except
     * production, where a violation degrades a page instead of breaking it, and
     * except inside Nova, which lazy loads through its own field resolution and
     * is not code this application owns.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        JsonResource::withoutWrapping();

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Model::preventSilentlyDiscardingAttributes(
            ! app()->isProduction(),
        );

        Model::preventLazyLoading(
            ! app()->isProduction(),
        );

        $this->configureLazyLoadingViolations();

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    /**
     * Let a lazy load inside Nova through, and throw on every other one.
     *
     * Nova resolves fields off models it did not eager load, so the guardrail
     * would fire on Nova's own internals rather than on application code. It is
     * scoped out per request instead of being switched off globally: the
     * prevention is worth far more on the four verticals still to be built than
     * a green /nova is worth losing it.
     *
     * The callback replaces Model::handleLazyLoadingViolation() wholesale, so it
     * has to restore that method's own early return for models that are not
     * persisted or were just created — those have no relation to load and the
     * framework never considers them a violation.
     */
    protected function configureLazyLoadingViolations(): void
    {
        Model::handleLazyLoadingViolationUsing(function (Model $model, string $relation): void {
            if (! $model->exists || $model->wasRecentlyCreated) {
                return;
            }

            if ($this->isNovaRequest()) {
                return;
            }

            throw new LazyLoadingViolationException($model, $relation);
        });
    }

    /**
     * Whether the current request is being served by Nova.
     *
     * Delegated to Nova's own matcher rather than re-derived from
     * `config('nova.path')`. Nova registers 110 routes and only 32 of them sit
     * under `nova/`: 76 are `nova-api/*` (every resource index, detail,
     * relatable and action field resolution — i.e. all the code that actually
     * lazy loads) and 2 are `nova-vendor/*`. A `$request->is($path, $path.'/*')`
     * check returned false for all 78 of those, so the scope-out covered the SPA
     * shell and nothing that renders a field. Util::isNovaRequest() knows all
     * three prefixes plus the `nova.domain` install, and tracks the package.
     *
     * ## `runningInConsole()` is not "there is no request to ask"
     *
     * It is `PHP_SAPI === 'cli'`, which is true for the whole test suite —
     * including a simulated HTTP request, which is a real request object routed
     * through the real kernel. A bare `runningInConsole()` early return
     * therefore made this method answer false for every `nova-api/*` request
     * under Pest, and the exemption was dead in exactly the place it needed
     * proving: `GET /nova-api/users` 500'd with LazyLoadingViolationException
     * out of the Avatar field's getMedia(). Live it looked fine, and the suite
     * agreed with it because Builder::hydrate() leaves the guard off below two
     * rows and every Nova fixture seeded one.
     *
     * `runningUnitTests()` is what separates the two: an artisan command, a
     * queue worker or a scheduler tick is console *and* not a test, and gets
     * the early return because its `request` is the placeholder
     * SetRequestForConsole builds from `config('app.url')`. A test is console
     * and a test, so the question is passed to Nova's own matcher against the
     * request the kernel actually bound — which answers false for the
     * application's own routes just as it does in the browser.
     */
    protected function isNovaRequest(): bool
    {
        if ($this->app->runningInConsole() && ! $this->app->runningUnitTests()) {
            return false;
        }

        return Util::isNovaRequest($this->app->make('request'));
    }
}
