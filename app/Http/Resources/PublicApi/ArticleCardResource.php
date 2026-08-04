<?php

namespace App\Http\Resources\PublicApi;

use App\Models\PublicArticle;
use App\Modules\PublicApi\Read\Support\PublicMediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ArticleCardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var PublicArticle $article */
        $article = $this->resource;

        return [
            'id' => $article->public_id,
            'slug' => $article->slug,
            'title' => $this->plainText($article->title, 200),
            'excerpt' => $this->plainText($article->excerpt, 1000),
            'cover_image_url' => app(PublicMediaUrl::class)->article($article),
            'published_at' => $article->published_at?->toIso8601String(),
            'updated_at' => $article->updated_at?->toIso8601String(),
        ];
    }

    protected function plainText(mixed $value, int $maximumLength): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim(strip_tags($value));

        return $value === '' ? null : mb_substr($value, 0, $maximumLength);
    }
}
