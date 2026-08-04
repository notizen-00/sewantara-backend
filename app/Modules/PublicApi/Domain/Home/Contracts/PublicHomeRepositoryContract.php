<?php

namespace App\Modules\PublicApi\Domain\Home\Contracts;

use Illuminate\Support\Collection;

interface PublicHomeRepositoryContract
{
    public function settings(string $group): array;

    public function categories(int $limit = 12): Collection;

    public function featuredProducts(int $limit = 8): Collection;

    public function latestArticles(int $limit = 6): Collection;

    public function productCount(): int;

    public function bookingCount(): int;

    public function customerCount(): int;

    public function averageRating(): float;
}
