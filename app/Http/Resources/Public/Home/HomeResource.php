<?php

namespace App\Http\Resources\Public\Home;

use App\Modules\PublicApi\DTO\Home\PublicHomeData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HomeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var PublicHomeData $data */
        $data = $this->resource;

        return [
            'tenant' => $data->tenant,

            'hero' => $data->hero,

            'categories' => CategoryCardResource::collection(
                $data->categories,
            )->resolve($request),

            'featuredProducts' => ProductCardResource::collection(
                $data->featuredProducts,
            )->resolve($request),

            'promotion' => $data->promotion,

            'howToBook' => $data->howToBook,

            'testimonials' => $data->testimonials,

            'faqs' => $data->faqs,

            'paymentMethods' => $data->tenant['paymentMethods'] ?? [],

            'blog' => ArticleCardResource::collection(
                $data->articles,
            )->resolve($request),

            'stats' => $data->stats,

            'cta' => $data->cta,
        ];
    }
}
