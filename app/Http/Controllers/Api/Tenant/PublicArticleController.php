<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StorePublicArticleRequest;
use App\Http\Requests\Tenant\StorePublicArticleCoverRequest;
use App\Http\Requests\Tenant\UpdatePublicArticleRequest;
use App\Models\PublicArticle;
use App\Modules\PublicApi\Read\Support\SafePublicHtml;
use App\Support\TenantPrivateMedia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

class PublicArticleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $articles = PublicArticle::query()
            ->when(
                $request->filled('q'),
                fn ($query) => $query->where(
                    fn ($query) => $query
                        ->where('title', 'ilike', '%'.$request->string('q')->toString().'%')
                        ->orWhere('slug', 'ilike', '%'.$request->string('q')->toString().'%'),
                ),
            )
            ->latest('published_at')
            ->latest('id')
            ->paginate(min(max($request->integer('per_page', 20), 1), 100));

        return response()->json(['success' => true, 'data' => $articles]);
    }

    public function store(
        StorePublicArticleRequest $request,
        SafePublicHtml $sanitizer,
    ): JsonResponse {
        $attributes = $this->prepare(
            $request->validated(),
            $sanitizer,
        );
        $article = PublicArticle::query()->create([
            'tenant_id' => (string) tenant('id'),
            ...$attributes,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Artikel berhasil dibuat.',
            'data' => $article,
        ], 201);
    }

    public function show(PublicArticle $publicArticle): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $publicArticle,
        ]);
    }

    public function update(
        UpdatePublicArticleRequest $request,
        PublicArticle $publicArticle,
        SafePublicHtml $sanitizer,
    ): JsonResponse {
        $publicArticle->update($this->prepare(
            $request->validated(),
            $sanitizer,
            $publicArticle,
        ));

        return response()->json([
            'success' => true,
            'message' => 'Artikel berhasil diperbarui.',
            'data' => $publicArticle->refresh(),
        ]);
    }

    public function destroy(
        PublicArticle $publicArticle,
        TenantPrivateMedia $media,
    ): JsonResponse
    {
        $coverPath = $publicArticle->cover_image_path;
        $publicArticle->delete();
        $media->delete($coverPath);

        return response()->json([
            'success' => true,
            'message' => 'Artikel berhasil dihapus.',
            'data' => null,
        ]);
    }

    public function cover(
        StorePublicArticleCoverRequest $request,
        PublicArticle $publicArticle,
        TenantPrivateMedia $media,
    ): JsonResponse {
        $path = $media->store(
            $request->file('image'),
            'articles/'.$publicArticle->public_id,
        );
        $oldPath = $publicArticle->cover_image_path;

        try {
            $publicArticle->update(['cover_image_path' => $path]);
        } catch (Throwable $exception) {
            $media->delete($path);

            throw $exception;
        }

        $media->delete($oldPath);

        return response()->json([
            'success' => true,
            'message' => 'Sampul artikel berhasil diperbarui.',
            'data' => $publicArticle->refresh(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function prepare(
        array $attributes,
        SafePublicHtml $sanitizer,
        ?PublicArticle $article = null,
    ): array {
        if (array_key_exists('body_html', $attributes)) {
            $attributes['body_html'] = $sanitizer->sanitize(
                $attributes['body_html'],
            );
        }

        if ($article === null) {
            $attributes['slug'] = $this->uniqueSlug(
                $attributes['slug'] ?? (string) $attributes['title'],
            );
        }

        $publishing = ($attributes['is_published'] ?? $article?->is_published)
            === true;

        if ($publishing && empty($attributes['published_at'])
            && $article?->published_at === null) {
            $attributes['published_at'] = now();
        }

        if (array_key_exists('is_published', $attributes)
            && ! $attributes['is_published']) {
            $attributes['published_at'] = null;
        }

        return $attributes;
    }

    private function uniqueSlug(string $value): string
    {
        $base = Str::slug($value) ?: 'article';
        $slug = $base;
        $suffix = 1;

        while (PublicArticle::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
