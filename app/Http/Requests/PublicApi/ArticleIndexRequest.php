<?php

namespace App\Http\Requests\PublicApi;

class ArticleIndexRequest extends PublicReadRequest
{
    protected function prepareForValidation(): void
    {
        $this->normalizeIntegers(['page', 'per_page']);
        $normalized = [
            'page' => $this->input('page', 1),
            'per_page' => $this->input('per_page', 20),
        ];

        if ($this->filled('q')) {
            $search = $this->input('q');
            $normalized['q'] = is_string($search) ? trim($search) : $search;
        }

        $this->merge($normalized);
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'page' => ['required', 'integer', 'min:1'],
            'per_page' => ['required', 'integer', 'min:1', 'max:50'],
        ];
    }

    protected function allowedQueryKeys(): array
    {
        return ['q', 'page', 'per_page'];
    }
}
