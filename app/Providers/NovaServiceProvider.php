<?php

namespace App\Providers;

use App\Models\Admin;
use App\Models\Report;
use App\Nova\Admin as AdminResource;
use App\Nova\Breed;
use App\Nova\Category;
use App\Nova\Comment;
use App\Nova\Dashboards\Main;
use App\Nova\Dashboards\Moderation;
use App\Nova\Pet;
use App\Nova\Report as ReportResource;
use App\Nova\Review;
use App\Nova\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Laravel\Fortify\Features;
use Laravel\Nova\Dashboard;
use Laravel\Nova\Menu\MenuItem;
use Laravel\Nova\Menu\MenuSection;
use Laravel\Nova\Nova;
use Laravel\Nova\NovaApplicationServiceProvider;
use Laravel\Nova\Tool;

class NovaServiceProvider extends NovaApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        Nova::mainMenu(fn (Request $request): array => $this->menu($request));
    }

    /**
     * Register the configurations for Laravel Fortify.
     *
     * Only password updates. Nova's Fortify features act on the guard in
     * `config('nova.guard')`, which is `admin`, so anything enabled here runs
     * against App\Models\Admin — and `admins` has no two-factor or passkey
     * columns, so those features would fatal rather than degrade. Email
     * verification is off for the same reason routes() disables its routes:
     * admins are created by another admin, not self-served.
     */
    protected function fortify(): void
    {
        Nova::fortify()
            ->features([
                Features::updatePasswords(),
            ])
            ->register();
    }

    /**
     * Build the Nova sidebar.
     *
     * Three sections, ordered by how often somebody opens them rather than
     * alphabetically: the moderation queue first because it is the only part
     * of this panel with a backlog, then the catalog, then accounts. The
     * Reports item carries a live count of everything still pending, so the
     * queue announces itself without the dashboard being open.
     *
     * That count is taken once here rather than inside the two badge closures,
     * which are both evaluated when the menu is serialised: `withBadgeIf` calls
     * the value callback and the condition callback separately, so a query in
     * each would cost two per Nova page load instead of one.
     *
     * @return array<int, MenuSection>
     */
    public function menu(Request $request): array
    {
        $pendingReports = Report::query()->pending()->count();

        return [
            MenuSection::dashboard(Main::class)->icon('chart-bar'),

            MenuSection::make('Moderation', [
                MenuItem::dashboard(Moderation::class),
                MenuItem::resource(ReportResource::class)
                    ->withBadgeIf(
                        fn (): int => $pendingReports,
                        'danger',
                        fn (): bool => $pendingReports > 0,
                    ),
                MenuItem::resource(Comment::class),
                MenuItem::resource(Review::class),
            ])->icon('flag')->collapsable(),

            MenuSection::make('Catalog', [
                MenuItem::resource(Pet::class),
                MenuItem::resource(Category::class),
                MenuItem::resource(Breed::class),
            ])->icon('rectangle-group')->collapsable(),

            MenuSection::make('Accounts', [
                MenuItem::resource(User::class),
                MenuItem::resource(AdminResource::class),
            ])->icon('users')->collapsable(),
        ];
    }

    /**
     * Register the Nova routes.
     */
    protected function routes(): void
    {
        Nova::routes()
            ->withAuthenticationRoutes()
            ->withPasswordResetRoutes()
            ->withoutEmailVerificationRoutes()
            ->register();
    }

    /**
     * Register the Nova gate.
     *
     * This gate determines who can access Nova in non-local environments.
     *
     * **The check is in the body, not in the type hint.** This used to read
     * `fn (Admin $admin): bool => true` with a note saying the hint *was* the
     * gate, because "Laravel's Gate refuses to invoke a callback whose first
     * parameter cannot accept the acting user". That is true of a **policy
     * method** — Gate::callPolicyMethod() inspects the signature before calling
     * — and false of a **closure ability**: Gate::callAuthCallback() does
     * `$callback($user, ...$arguments)` with no inspection at all.
     *
     * So a member did reach the closure, and PHP threw
     * `TypeError: Argument #1 ($admin) must be of type App\Models\Admin,
     * App\Models\User given`. Measured with `nova.guard` nulled: access was
     * still denied, but as a **500 rather than a 403** — the one failure mode a
     * belt-and-braces defence must not have, because it is indistinguishable
     * from the application being broken and it is loud in exactly the situation
     * (a misconfigured guard) where the operator most needs a clear answer.
     *
     * `mixed` plus an explicit `instanceof` is the version that degrades
     * properly. It also keeps guest handling right by accident of the same
     * change: an untyped-or-nullable first parameter is what makes Laravel's
     * Gate offer the ability to guests, and `null instanceof Admin` is false.
     *
     * It remains belt and braces rather than the only defence.
     * `config('nova.guard')` is `admin`, so Nova's Authenticate middleware
     * already resolves the user from the `admins` provider and a member never
     * reaches the gate at all. Both are kept: the guard is a config value
     * somebody could clear, and Laravel\Nova\Util::userGuard() falls back to
     * `config('auth.defaults.guard')` — i.e. `web` — when it is null. The
     * legacy app had this gate with a body of `return true` and nothing else;
     * verified in petconnect-old/app/Providers/NovaServiceProvider.php.
     *
     * Every admin is a full moderator; there are no roles on the `admins`
     * table and inventing one here would be a schema decision made in a
     * provider. Per-resource limits live in App\Nova\Policies.
     */
    protected function gate(): void
    {
        Gate::define('viewNova', fn (mixed $user): bool => $user instanceof Admin);
    }

    /**
     * Get the dashboards that should be listed in the Nova sidebar.
     *
     * @return array<int, Dashboard>
     */
    protected function dashboards(): array
    {
        return [
            Main::make(),
            Moderation::make(),
        ];
    }

    /**
     * Get the tools that should be listed in the Nova sidebar.
     *
     * @return array<int, Tool>
     */
    public function tools(): array
    {
        return [];
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        parent::register();
    }
}
