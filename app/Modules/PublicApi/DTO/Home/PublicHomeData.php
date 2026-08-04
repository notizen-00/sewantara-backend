<?php

namespace App\Modules\PublicApi\DTO\Home;

use Illuminate\Support\Collection;

readonly class PublicHomeData
{
    public function __construct(
        public array $tenant,
        public array $hero,
        public Collection $categories,
        public Collection $featuredProducts,
        public ?array $promotion,
        public array $howToBook,
        public array $testimonials,
        public array $faqs,
        public Collection $articles,
        public array $stats,
        public array $cta,
    ) {}
}
