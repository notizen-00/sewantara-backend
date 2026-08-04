<?php

namespace App\Modules\PublicApi\Read\Support;

use App\Models\Category;
use App\Models\ProductImage;
use App\Models\PublicArticle;
use App\Models\TenantSetting;
use App\Support\TenantPrivateMedia;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class PublicMediaResolver
{
    /** @var list<string> */
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/x-icon',
        'image/vnd.microsoft.icon',
    ];

    public function resolve(string $kind, string $identifier): ?PublicMediaAsset
    {
        $path = match ($kind) {
            'branding' => $this->brandingPath($identifier),
            'categories' => $this->categoryPath($identifier),
            'product-images' => $this->productImagePath($identifier),
            'articles' => $this->articlePath($identifier),
            default => null,
        };

        if (! is_string($path) || ! $this->validPath($path)) {
            return null;
        }

        try {
            $disk = Storage::disk(TenantPrivateMedia::DISK);

            if (! $disk->exists($path)) {
                return null;
            }

            $mimeType = (string) $disk->mimeType($path);

            if (! in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
                return null;
            }

            return new PublicMediaAsset(
                disk: TenantPrivateMedia::DISK,
                path: $path,
                mimeType: $mimeType,
                size: (int) $disk->size($path),
                lastModified: (int) $disk->lastModified($path),
            );
        } catch (Throwable) {
            return null;
        }
    }

    private function brandingPath(string $identifier): ?string
    {
        if (! in_array($identifier, ['logo', 'favicon'], true)) {
            return null;
        }

        $setting = TenantSetting::query()
            ->where('group', 'branding')
            ->where('key', $identifier.'_path')
            ->first(['value']);

        return is_string($setting?->value) ? $setting->value : null;
    }

    private function categoryPath(string $identifier): ?string
    {
        return Category::query()
            ->publiclyVisible()
            ->where('public_id', $identifier)
            ->value('image_path');
    }

    private function productImagePath(string $identifier): ?string
    {
        return ProductImage::query()
            ->publiclyVisible()
            ->where('public_id', $identifier)
            ->value('image_path');
    }

    private function articlePath(string $identifier): ?string
    {
        return PublicArticle::query()
            ->published()
            ->where('public_id', $identifier)
            ->value('cover_image_path');
    }

    private function validPath(string $path): bool
    {
        $path = str_replace('\\', '/', trim($path, '/'));

        return $path !== ''
            && ! str_contains($path, '..')
            && preg_match('/^[A-Za-z0-9._\/-]+$/', $path) === 1;
    }
}
