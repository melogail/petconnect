<?php

namespace App\Providers;

use App\MediaLibrary\MediaPathGenerator;
use App\Models\Admin;
use App\Models\Breed;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Pet;
use App\Models\Review;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\LazyLoadingViolationException;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * MediaPathGenerator is scoped rather than resolved fresh: medialibrary
     * calls app() on it once per generated path, and a request-lifetime
     * instance lets it memoise its fallback owner lookups without the memo
     * outliving a request or a refreshed test database.
     */
    public function register(): void
    {
        $this->app->scoped(MediaPathGenerator::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureMorphMap();
        $this->configureRateLimiters();
    }

    /**
     * Define the named rate limiters the routes attach.
     *
     * `pet-likes` guards POST pets/{pet}/like. A like writes a row that
     * LikeObserver turns into a database notification for the owner, so an
     * unthrottled tap loop is a notification flood rather than a wasted write;
     * the legacy app throttled nothing at all. Keyed by user id, falling back
     * to the IP so a request that somehow arrives unauthenticated is still
     * bounded.
     */
    protected function configureRateLimiters(): void
    {
        RateLimiter::for('pet-likes', fn (Request $request): Limit => Limit::perMinute(30)
            ->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));
    }

    /**
     * Map every morphable model to a short, stable alias.
     *
     * Polymorphic columns (likes, saves, comments, reviews, reports, media,
     * notifications) store these aliases instead of fully qualified class
     * names, so renaming or moving a model cannot orphan existing rows.
     */
    protected function configureMorphMap(): void
    {
        Relation::enforceMorphMap([
            'admin' => Admin::class,
            'breed' => Breed::class,
            'category' => Category::class,
            'comment' => Comment::class,
            'pet' => Pet::class,
            'review' => Review::class,
            'user' => User::class,
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
     * scoped out by request path instead of being switched off globally: the
     * prevention is worth far more on the four verticals still to be built than
     * a green /nova is worth losing it.
     */
    protected function configureLazyLoadingViolations(): void
    {
        Model::handleLazyLoadingViolationUsing(function (Model $model, string $relation): void {
            if ($this->isNovaRequest()) {
                return;
            }

            throw new LazyLoadingViolationException($model, $relation);
        });
    }

    /**
     * Whether the current request is being served by Nova.
     */
    protected function isNovaRequest(): bool
    {
        if ($this->app->runningInConsole()) {
            return false;
        }

        $path = trim((string) config('nova.path', '/nova'), '/');

        if ($path === '') {
            return false;
        }

        return $this->app->make('request')->is($path, $path.'/*');
    }
}
