<?php

namespace App\Http\Resources\Api\Public;

use App\Models\PublicArticle;
use App\Modules\PublicApi\Read\Support\PublicMediaUrl;
use App\Modules\PublicApi\Read\Support\SafePublicHtml;

final class PublicArticleResource
{
    /** @return array<string, mixed> */
    public static function card(PublicArticle $article): array
    {
        return [
            'id' => $article->public_id,
            'slug' => $article->slug,
            'title' => self::text($article->title, 200),
            'excerpt' => self::text($article->excerpt, 1000),
            'cover_image_url' => app(PublicMediaUrl::class)->article($article),
            'published_at' => $article->published_at?->toIso8601String(),
            'updated_at' => $article->updated_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public static function detail(PublicArticle $article): array
    {
        return [
            ...self::card($article),
            'body_html' => app(SafePublicHtml::class)->sanitize(
                $article->body_html,
            ),
            'seo' => [
                'title' => self::text($article->seo_title, 200)
                    ?: self::text($article->title, 200),
                'description' => self::text(
                    $article->seo_description,
                    1000,
                ) ?: self::text($article->excerpt, 1000),
            ],
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
