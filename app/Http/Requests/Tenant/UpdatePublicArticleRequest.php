<?php

namespace App\Http\Requests\Tenant;

use App\Models\PublicArticle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePublicArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $article = $this->route('publicArticle');

        return [
            'slug' => [
                'sometimes',
                'string',
                'max:200',
                'alpha_dash',
                Rule::unique(PublicArticle::class, 'slug')
                    ->where('tenant_id', (string) tenant('id'))
                    ->ignore($article instanceof PublicArticle
                        ? $article->getKey()
                        : null),
            ],
            'title' => ['sometimes', 'string', 'max:200'],
            'excerpt' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'body_html' => ['sometimes', 'string', 'max:100000'],
            'cover_image_path' => [
                'sometimes',
                'nullable',
                'string',
                'max:500',
                'regex:/^(?!.*\.\.)(?!\/)[A-Za-z0-9._\/-]+$/',
            ],
            'seo_title' => ['sometimes', 'nullable', 'string', 'max:200'],
            'seo_description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'is_published' => ['sometimes', 'boolean'],
            'published_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
