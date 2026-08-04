<?php

namespace App\Modules\PublicApi\Read\Support;

use App\Models\Category;
use App\Models\ProductImage;
use App\Models\PublicArticle;

final class PublicMediaUrl
{
    public function branding(string $name, ?string $path): ?string
    {
        if (! in_array($name, ['logo', 'favicon'], true) || blank($path)) {
            return null;
        }

        return $this->url('branding', $name);
    }

    public function category(Category $category): ?string
    {
        return blank($category->image_path) || blank($category->public_id)
            ? null
            : $this->url('categories', (string) $category->public_id);
    }

    public function productImage(ProductImage $image): ?string
    {
        return blank($image->image_path) || blank($image->public_id)
            ? null
            : $this->url('product-images', (string) $image->public_id);
    }

    public function article(PublicArticle $article): ?string
    {
        return blank($article->cover_image_path) || blank($article->public_id)
            ? null
            : $this->url('articles', (string) $article->public_id);
    }

    private function url(string $kind, string $identifier): string
    {
        return rtrim((string) config(
            'public-api.public_media_base_url',
            '/api/public',
        ), '/')
            .'/media/'.rawurlencode($kind)
            .'/'.rawurlencode($identifier);
    }
}
