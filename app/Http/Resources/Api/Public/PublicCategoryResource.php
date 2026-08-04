<?php

namespace App\Http\Resources\Api\Public;

use App\Models\Category;
use App\Modules\PublicApi\Read\Support\PublicMediaUrl;

final class PublicCategoryResource
{
    /** @return array<string, mixed> */
    public static function make(Category $category): array
    {
        $parent = $category->relationLoaded('parent')
            ? $category->parent
            : null;

        return [
            'id' => $category->public_id,
            'slug' => $category->slug,
            'name' => $category->name,
            'description' => self::text($category->description, 1000),
            'image_url' => app(PublicMediaUrl::class)->category($category),
            'product_count' => isset($category->product_count)
                ? (int) $category->product_count
                : null,
            'parent' => $parent instanceof Category ? [
                'id' => $parent->public_id,
                'slug' => $parent->slug,
                'name' => $parent->name,
            ] : null,
        ];
    }

    private static function text(mixed $value, int $limit): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim(strip_tags($value));

        return $value === '' ? null : mb_substr($value, 0, $limit);
    }
}
