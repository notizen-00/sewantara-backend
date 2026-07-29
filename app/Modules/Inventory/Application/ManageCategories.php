<?php

namespace App\Modules\Inventory\Application;

use App\Models\Category;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class ManageCategories
{
    public function paginate(
        ?string $search,
        ?string $parentId,
        ?bool $isActive,
        bool $rootsOnly,
        int $perPage = 20,
    ): LengthAwarePaginator {
        return Category::query()
            ->with('parent')
            ->withCount(['children', 'products'])
            ->when(
                $search,
                fn ($query, string $value) => $query
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
        $attributes['slug'] ??= Str::slug($attributes['name']);
        $attributes['slug'] = $this->uniqueSlug($attributes['slug']);
        $attributes['sort_order'] ??= 0;
        $attributes['is_active'] ??= true;

        return Category::create($attributes)->load('parent');
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
