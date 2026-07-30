<?php

namespace App\Providers;

use App\Support\PostmanCollectionBuilder;
use Illuminate\Support\ServiceProvider;
use YasinTgh\LaravelPostman\Collections\Builder;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->extend(
            Builder::class,
            fn (Builder $builder): PostmanCollectionBuilder => new PostmanCollectionBuilder($builder),
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // `php artisan migrate` must only discover central migrations.
        $this->loadMigrationsFrom(database_path('migrations/central'));
    }
}
