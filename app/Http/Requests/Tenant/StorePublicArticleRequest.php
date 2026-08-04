<?php

namespace App\Http\Requests\Tenant;

use App\Models\PublicArticle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePublicArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'slug' => [
                'nullable',
                'string',
                'max:200',
                'alpha_dash',
                Rule::unique(PublicArticle::class, 'slug')
                    ->where('tenant_id', (string) tenant('id')),
            ],
            'title' => ['required', 'string', 'max:200'],
            'excerpt' => ['nullable', 'string', 'max:1000'],
            'body_html' => ['required', 'string', 'max:100000'],
            'cover_image_path' => [
                'nullable',
                'string',
                'max:500',
                'regex:/^(?!.*\.\.)(?!\/)[A-Za-z0-9._\/-]+$/',
            ],
            'seo_title' => ['nullable', 'string', 'max:200'],
            'seo_description' => ['nullable', 'string', 'max:1000'],
            'is_published' => ['nullable', 'boolean'],
            'published_at' => ['nullable', 'date'],
        ];
    }
}
