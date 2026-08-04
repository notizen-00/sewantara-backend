<?php

namespace App\Http\Resources\Public\Home;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ArticleCardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) (
                $this->public_id
                ?: $this->getKey()
            ),

            'slug' => (string) $this->slug,

            'title' => (string) $this->title,

            'excerpt' => (string) (
                $this->excerpt
                ?? ''
            ),

            'image' => [
                'url' => '',
                'alt' => (string) $this->title,
            ],

            'category' => 'Artikel',

            'publishedAt' => $this->published_at
                ?->toIso8601String(),

            'readingTimeMinutes' => max(
                1,
                (int) ceil(
                    str_word_count(
                        strip_tags(
                            (string) $this->body_html,
                        ),
                    ) / 200,
                ),
            ),
        ];
    }
}
