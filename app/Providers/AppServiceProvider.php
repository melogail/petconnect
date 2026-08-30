<?php

namespace App\Providers;

use App\Models\Admin;
use App\Models\Breed;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Pet;
use App\Models\Review;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureMorphMap();
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
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

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
}
