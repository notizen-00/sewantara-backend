<?php

namespace App\Http\Resources\PublicApi;

use App\Models\Category;
use App\Models\Product;
use App\Models\PublicArticle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class HomeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $home = is_array($this->resource) ? $this->resource : [];

        return [
            'hero' => $home['hero'] ?? null,
            'categories' => $this->mapCollection(
                $home['categories'] ?? collect(),
                fn (Category $category): array => (new CategoryResource($category))
                    ->toArray($request),
            ),
            'featured_products' => $this->mapCollection(
                $home['featured_products'] ?? collect(),
                fn (Product $product): array => (new ProductCardResource($product))
                    ->toArray($request),
            ),
            'promotions' => $home['promotions'] ?? [],
            'how_to_book' => $home['how_to_book'] ?? [],
            'testimonials' => $home['testimonials'] ?? [],
            'faq' => $home['faq'] ?? [],
            'latest_articles' => $this->mapCollection(
                $home['latest_articles'] ?? collect(),
                fn (PublicArticle $article): array => (new ArticleCardResource($article))
                    ->toArray($request),
            ),
            'cta' => $home['cta'] ?? null,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function mapCollection(mixed $items, callable $transform): array
    {
        return ($items instanceof Collection ? $items : collect())
            ->map($transform)
            ->values()
            ->all();
    }
}
