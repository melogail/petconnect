<?php

namespace App\Providers;

use App\Repositories\Eloquent\PetRepository;
use App\Repositories\Interfaces\PetRepositoryInterface;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(
            PetRepositoryInterface::class,
            PetRepository::class
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
