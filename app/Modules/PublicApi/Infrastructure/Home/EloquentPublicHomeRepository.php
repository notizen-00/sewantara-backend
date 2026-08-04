<?php

namespace App\Modules\PublicApi\Infrastructure\Home;

use App\Models\Booking;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\PublicArticle;
use App\Models\TenantSetting;
use App\Modules\PublicApi\Domain\Home\Contracts\PublicHomeRepositoryContract;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class EloquentPublicHomeRepository implements PublicHomeRepositoryContract
{
    public function settings(string $group): array
    {
        return TenantSetting::query()
            ->where('group', $group)
            ->get()
            ->mapWithKeys(
                fn(TenantSetting $setting): array => [
                    $setting->key => $setting->value,
                ],
            )
            ->all();
    }

    public function categories(int $limit = 12): Collection
    {
        return Category::query()
            ->publiclyVisible()
            ->withCount([
                'products as product_count' => fn($query) => $query
                    ->publiclyVisible(),
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->limit($limit)
            ->get();
    }

    public function featuredProducts(int $limit = 8): Collection
    {
        return Product::query()
            ->publiclyVisible()
            ->where('is_featured', true)
            ->with([
                'category:id,public_id,name,slug',
                'images',
                'prices',
            ])
            ->latest('published_at')
            ->limit($limit)
            ->get();
    }

    public function latestArticles(int $limit = 6): Collection
    {
        return PublicArticle::query()
            ->published()
            ->latest('published_at')
            ->limit($limit)
            ->get();
    }

    public function productCount(): int
    {
        return Product::query()
            ->publiclyVisible()
            ->count();
    }

    public function bookingCount(): int
    {
        if (! Schema::hasTable('bookings')) {
            return 0;
        }

        return Booking::query()->count();
    }

    public function customerCount(): int
    {
        if (! Schema::hasTable('customers')) {
            return 0;
        }

        return Customer::query()->count();
    }

    public function averageRating(): float
    {
        if (! Schema::hasTable('reviews')) {
            return 0;
        }

        return (float) DB::table('reviews')
            ->where('is_published', true)
            ->avg('rating');
    }
}
