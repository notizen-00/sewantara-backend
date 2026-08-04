<?php

namespace App\Http\Resources\PublicApi;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = is_array($this->resource) ? $this->resource : [];

        return collect([
            'id',
            'slug',
            'name',
            'status',
            'tagline',
            'description',
            'logo_url',
            'favicon_url',
            'theme',
            'contact',
            'location',
            'operating_hours',
            'social_media',
            'payment_methods',
            'timezone',
            'locale',
            'currency',
            'features',
            'seo',
        ])->mapWithKeys(fn (string $key): array => [
            $key => $data[$key] ?? null,
        ])->all();
    }
}
