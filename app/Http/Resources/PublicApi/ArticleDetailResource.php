<?php

namespace App\Http\Resources\PublicApi;

use App\Models\PublicArticle;
use App\Modules\PublicApi\Read\Support\SafePublicHtml;
use Illuminate\Http\Request;

class ArticleDetailResource extends ArticleCardResource
{
    public function toArray(Request $request): array
    {
        /** @var PublicArticle $article */
        $article = $this->resource;

        return [
            ...parent::toArray($request),
            'body_html' => app(SafePublicHtml::class)->sanitize($article->body_html),
            'seo' => [
                'title' => $this->plainText(
                    $article->seo_title ?: $article->title,
                    200,
                ),
                'description' => $this->plainText(
                    $article->seo_description ?: $article->excerpt,
                    500,
                ),
            ],
        ];
    }
}
