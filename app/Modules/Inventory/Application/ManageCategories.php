<?php

namespace App\Modules\Inventory\Application;

use App\Models\Category;
use App\Support\TenantPrivateMedia;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Throwable;

class ManageCategories
{
    public function __construct(
        private readonly TenantPrivateMedia $media,
    ) {}

    public function paginate(
        ?string $search,
        ?int $parentId,
        ?bool $isActive,
        bool $rootsOnly,
        int $perPage = 20,
    ): LengthAwarePaginator {
        return Category::query()
            ->with('parent')
            ->withCount(['children', 'products'])
            ->when(
                $search,
                fn ($query, int $value) => $query
                    ->where('name', 'ilike', "%{$value}%"),
            )
            ->when(
                $parentId,
                fn ($query, string $value) => $query
                    ->where('parent_id', $value),
            )
            ->when(
                $rootsOnly,
                fn ($query) => $query->whereNull('parent_id'),
            )
            ->when(
                $isActive !== null,
                fn ($query) => $query->where('is_active', $isActive),
            )
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(min(max($perPage, 1), 100));
    }

    public function create(array $attributes): Category
    {
        $image = $attributes['image'] ?? null;
        unset($attributes['image']);

        $attributes['slug'] ??= Str::slug($attributes['name']);
        $attributes['slug'] = $this->uniqueSlug($attributes['slug']);
        $attributes['sort_order'] ??= 0;
        $attributes['is_active'] ??= true;

        $category = Category::create($attributes);

        if ($image instanceof UploadedFile) {
            try {
                return $this->updateImage($category, $image);
            } catch (Throwable $exception) {
                $category->forceDelete();

                throw $exception;
            }
        }

        return $category->load('parent');
    }

    public function detail(Category $category): Category
    {
        return $category
            ->load(['parent', 'children'])
            ->loadCount(['children', 'products']);
    }

    public function update(
        Category $category,
        array $attributes,
    ): Category {
        $category->update($attributes);

        return $this->detail($category->refresh());
    }

    public function delete(Category $category): void
    {
        $category->delete();
    }

    public function updateImage(
        Category $category,
        UploadedFile $image,
    ): Category {
        $path = $this->media->store(
            $image,
            "categories/{$category->getKey()}",
        );
        $oldPath = $category->image_path;

        try {
            $category->update(['image_path' => $path]);
        } catch (Throwable $exception) {
            $this->media->delete($path);

            throw $exception;
        }

        $this->media->delete($oldPath);

        return $this->detail($category->refresh());
    }

    public function deleteImage(Category $category): Category
    {
        $path = $category->image_path;
        $category->update(['image_path' => null]);
        $this->media->delete($path);

        return $this->detail($category->refresh());
    }

    private function uniqueSlug(string $value): string
    {
        $baseSlug = Str::slug($value) ?: 'category';
        $slug = $baseSlug;
        $suffix = 1;

        while (Category::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$suffix++;
        }

        return $slug;
    }
}
