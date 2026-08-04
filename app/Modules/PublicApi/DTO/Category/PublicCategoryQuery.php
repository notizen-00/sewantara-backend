<?php

namespace App\Modules\PublicApi\DTO\Category;

readonly class PublicCategoryQuery
{
    public function __construct(
        public ?string $search,
        public ?string $parentSlug,
        public bool $onlyParents,
        public bool $withChildren,
        public bool $withProductCount,
        public int $page,
        public int $perPage,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function fromArray(array $attributes): self
    {
        return new self(
            search: isset($attributes['search'])
                ? trim((string) $attributes['search'])
                : null,

            parentSlug: isset($attributes['parent'])
                ? trim((string) $attributes['parent'])
                : null,

            onlyParents: (bool) (
                $attributes['only_parents']
                ?? false
            ),

            withChildren: (bool) (
                $attributes['with_children']
                ?? false
            ),

            withProductCount: (bool) (
                $attributes['with_product_count']
                ?? true
            ),

            page: max(
                1,
                (int) ($attributes['page'] ?? 1),
            ),

            perPage: min(
                50,
                max(
                    1,
                    (int) ($attributes['per_page'] ?? 20),
                ),
            ),
        );
    }
}
