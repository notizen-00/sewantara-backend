<?php

namespace App\Providers;

use App\Models\Branch;
use App\Models\Category;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductPrice;
use App\Models\ProductUnit;
use App\Models\PublicArticle;
use App\Models\Tenant;
use App\Models\TenantBusinessProfile;
use App\Models\TenantPaymentMethod;
use App\Models\TenantSetting;
use App\Observers\InvalidatePublicContentCache;
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

        foreach ([
            Tenant::class,
            TenantBusinessProfile::class,
            TenantSetting::class,
            TenantPaymentMethod::class,
            Branch::class,
            Category::class,
            Product::class,
            ProductImage::class,
            ProductPrice::class,
            ProductUnit::class,
            InventoryStock::class,
            PublicArticle::class,
        ] as $model) {
            $model::observe(InvalidatePublicContentCache::class);
        }
    }
}
