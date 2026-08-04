<?php

namespace App\Modules\PublicApi\Read;

use App\Models\PublicArticle;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class PublicArticleService
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        return PublicArticle::query()
            ->published()
            ->when(
                $filters['q'] ?? null,
                function (Builder $articles, string $search): void {
                    $articles->where(function (Builder $articles) use ($search): void {
                        $pattern = '%'.mb_strtolower(trim($search)).'%';
                        $articles
                            ->whereRaw('LOWER(title) LIKE ?', [$pattern])
                            ->orWhereRaw("LOWER(COALESCE(excerpt, '')) LIKE ?", [$pattern]);
                    });
                },
            )
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate(
                perPage: (int) ($filters['per_page'] ?? 20),
                page: (int) ($filters['page'] ?? 1),
            );
    }

    public function findBySlug(string $slug): ?PublicArticle
    {
        return PublicArticle::query()
            ->published()
            ->where('slug', $slug)
            ->first();
    }

    public function latest(int $limit = 3): Collection
    {
        return PublicArticle::query()
            ->published()
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit(max(1, min($limit, 12)))
            ->get();
    }

    public function sitemapArticles(): Collection
    {
        return PublicArticle::query()
            ->published()
            ->orderBy('slug')
            ->get(['public_id', 'slug', 'updated_at', 'published_at']);
    }
}
