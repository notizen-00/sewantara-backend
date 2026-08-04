<?php

namespace App\Modules\PublicApi\Read;

use App\Models\Product;
use App\Models\PublicArticle;
use Illuminate\Http\Request;

final class PublicSitemapService
{
    public function __construct(
        private readonly PublicCatalogService $catalog,
        private readonly PublicArticleService $articles,
    ) {}

    /**
     * @return array{base_url: string, urls: list<array<string, mixed>>}
     */
    public function get(Request $request): array
    {
        $baseUrl = $this->baseUrl($request);
        $urls = [
            $this->entry($baseUrl.'/', now(), 'daily', 1.0),
            $this->entry($baseUrl.'/catalog', now(), 'daily', 0.9),
        ];

        foreach ($this->catalog->sitemapProducts() as $product) {
            if (! $product instanceof Product) {
                continue;
            }

            $urls[] = $this->entry(
                $baseUrl.'/catalog/'.rawurlencode($product->slug),
                $product->updated_at,
                'daily',
                0.8,
            );
        }

        $articles = $this->articles->sitemapArticles();

        if ($articles->isNotEmpty()) {
            $urls[] = $this->entry($baseUrl.'/blog', now(), 'daily', 0.7);
        }

        foreach ($articles as $article) {
            if (! $article instanceof PublicArticle) {
                continue;
            }

            $urls[] = $this->entry(
                $baseUrl.'/blog/'.rawurlencode($article->slug),
                $article->updated_at ?? $article->published_at,
                'weekly',
                0.7,
            );
        }

        return [
            'base_url' => $baseUrl,
            'urls' => $urls,
        ];
    }

    /** @return array<string, mixed> */
    private function entry(
        string $url,
        mixed $lastModified,
        string $changeFrequency,
        float $priority,
    ): array {
        return [
            'url' => $url,
            'last_modified' => $lastModified?->toIso8601String(),
            'change_frequency' => $changeFrequency,
            'priority' => $priority,
        ];
    }

    private function baseUrl(Request $request): string
    {
        $host = (string) $request->attributes->get('tenant_host');
        $scheme = app()->isProduction()
            ? 'https'
            : (parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'http');

        return $scheme.'://'.$host;
    }
}
