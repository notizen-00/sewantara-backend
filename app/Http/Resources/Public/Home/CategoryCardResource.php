<?php

namespace App\Http\Resources\Public\Home;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryCardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) (
                $this->public_id
                ?: $this->getKey()
            ),

            'slug' => (string) $this->slug,

            'name' => (string) $this->name,

            'description' => (string) (
                $this->description
                ?? ''
            ),

            'icon' => '',

            'image' => [
                'url' => (string) (
                    $this->image_url
                    ?? ''
                ),

                'alt' => (string) $this->name,
            ],

            'productCount' => (int) (
                $this->product_count
                ?? 0
            ),
        ];
    }
}
