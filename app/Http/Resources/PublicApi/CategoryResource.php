<?php

namespace App\Http\Resources\PublicApi;

use App\Models\Category;
use App\Modules\PublicApi\Read\Support\PublicMediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Category $category */
        $category = $this->resource;
        $parent = $category->relationLoaded('parent') ? $category->parent : null;

        return [
            'id' => $category->public_id,
            'slug' => $category->slug,
            'name' => $category->name,
            'description' => $this->plainText($category->description, 2000),
            'image_url' => app(PublicMediaUrl::class)->category($category),
            'parent' => $parent === null ? null : [
                'id' => $parent->public_id,
                'slug' => $parent->slug,
                'name' => $parent->name,
            ],
            'product_count' => (int) ($category->product_count ?? 0),
        ];
    }

    private function plainText(mixed $value, int $maximumLength): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim(strip_tags($value));

        return $value === '' ? null : mb_substr($value, 0, $maximumLength);
    }
}
