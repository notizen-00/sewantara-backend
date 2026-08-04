<?php

namespace App\Modules\PublicApi\DTO\Catalog;

readonly class PublicProductData
{
    /**
     * @param list<array<string, mixed>> $images
     * @param list<string> $badges
     * @param list<array{label:string,value:string}> $specifications
     */
    public function __construct(
        public string $id,
        public string $slug,
        public string $name,
        public string $shortDescription,
        public string $description,
        public array $category,
        public array $images,
        public int $priceAmount,
        public string $currency,
        public string $pricingUnit,
        public string $pricingUnitLabel,
        public string $bookingMode,
        public array $bookingRules,
        public array $availability,
        public array $rating,
        public array $badges,
        public array $specifications,
        public bool $featured,
        public array $seo,
    ) {}
}
