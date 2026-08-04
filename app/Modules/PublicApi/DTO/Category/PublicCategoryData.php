<?php

namespace App\Modules\PublicApi\DTO\Category;

readonly class PublicCategoryData
{
    /**
     * @param  list<PublicCategoryData>  $children
     */
    public function __construct(
        public string $id,
        public string $slug,
        public string $name,
        public string $description,
        public ?string $parentId,
        public ?string $parentSlug,
        public ?string $imageUrl,
        public int $sortOrder,
        public int $productCount,
        public array $children = [],
    ) {}
}
