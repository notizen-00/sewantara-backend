<?php

namespace App\Modules\PublicApi\Read;

use App\Models\Tenant;
use App\Modules\PublicApi\Read\Support\PublicSettings;

final class PublicHomeService
{
    public function __construct(
        private readonly PublicTenantProfileService $profile,
        private readonly PublicCategoryService $categories,
        private readonly PublicCatalogService $catalog,
        private readonly PublicArticleService $articles,
        private readonly PublicSettings $settings,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function get(Tenant $tenant): array
    {
        $profile = $this->profile->get($tenant);
        $hero = $this->objectSetting('hero');
        $cta = $this->objectSetting('cta');

        return [
            'hero' => [
                'title' => $this->safeString(
                    $this->settings->value(
                        'homepage',
                        'hero_title',
                        $hero['title'] ?? null,
                    ),
                    $profile['name'],
                    200,
                ),
                'subtitle' => $this->safeString(
                    $this->settings->value(
                        'homepage',
                        'hero_subtitle',
                        $hero['subtitle'] ?? null,
                    ),
                    $profile['tagline'],
                    500,
                ),
                'image_url' => $profile['logo_url'],
                'action' => [
                    'label' => $this->safeString(
                        $hero['action_label'] ?? null,
                        'Lihat katalog',
                        80,
                    ),
                    'href' => '/catalog',
                ],
            ],
            'categories' => $this->categories->all(8),
            'featured_products' => $this->catalog->featured(8),
            // Promotion truth requires the pricing/quote domain. Never emit
            // arbitrary tenant JSON as an authoritative active promotion.
            'promotions' => [],
            'how_to_book' => $this->howToBook(),
            'testimonials' => $this->testimonials(),
            'faq' => $this->faq(),
            'latest_articles' => $this->articles->latest(3),
            'cta' => [
                'title' => $this->safeString(
                    $cta['title'] ?? null,
                    'Siap melakukan pemesanan?',
                    200,
                ),
                'description' => $this->safeString(
                    $cta['description'] ?? null,
                    'Pilih produk dan cek ketersediaannya sekarang.',
                    500,
                ),
                'label' => $this->safeString(
                    $this->settings->value(
                        'homepage',
                        'cta_label',
                        $cta['label'] ?? null,
                    ),
                    'Mulai sekarang',
                    80,
                ),
                'href' => $this->safeHref($this->settings->value(
                    'homepage',
                    'cta_url',
                    $cta['href'] ?? '/catalog',
                )),
            ],
        ];
    }

    /** @return list<array{title: string, description: string}> */
    private function howToBook(): array
    {
        $configured = $this->settings->value('homepage', 'how_to_book', []);
        $items = $this->safeList($configured, function (array $item): ?array {
            $title = $this->safeString($item['title'] ?? null, null, 120);
            $description = $this->safeString(
                $item['description'] ?? null,
                null,
                500,
            );

            return $title !== null && $description !== null
                ? compact('title', 'description')
                : null;
        }, 8);

        return $items !== [] ? $items : [
            [
                'title' => 'Pilih produk',
                'description' => 'Temukan produk yang sesuai dengan kebutuhan Anda.',
            ],
            [
                'title' => 'Cek ketersediaan',
                'description' => 'Tentukan periode pemesanan dan jumlah yang dibutuhkan.',
            ],
            [
                'title' => 'Konfirmasi pesanan',
                'description' => 'Periksa harga final dari sistem sebelum menyelesaikan pesanan.',
            ],
        ];
    }

    /** @return list<array{name: string, rating: int|null, quote: string}> */
    private function testimonials(): array
    {
        return $this->safeList(
            $this->settings->value('homepage', 'testimonials', []),
            function (array $item): ?array {
                $name = $this->safeString($item['name'] ?? null, null, 120);
                $quote = $this->safeString($item['quote'] ?? null, null, 1000);

                return $name !== null && $quote !== null ? [
                    'name' => $name,
                    'rating' => is_int($item['rating'] ?? null)
                        && $item['rating'] >= 1
                        && $item['rating'] <= 5
                            ? $item['rating']
                            : null,
                    'quote' => $quote,
                ] : null;
            },
            12,
        );
    }

    /** @return list<array{question: string, answer: string}> */
    private function faq(): array
    {
        return $this->safeList(
            $this->settings->value('homepage', 'faq', []),
            function (array $item): ?array {
                $question = $this->safeString(
                    $item['question'] ?? null,
                    null,
                    300,
                );
                $answer = $this->safeString(
                    $item['answer'] ?? null,
                    null,
                    2000,
                );

                return $question !== null && $answer !== null
                    ? compact('question', 'answer')
                    : null;
            },
            20,
        );
    }

    /** @return array<string, mixed> */
    private function objectSetting(string $key): array
    {
        $value = $this->settings->value('homepage', $key, []);

        return is_array($value) && ! array_is_list($value) ? $value : [];
    }

    /**
     * @param  callable(array<string, mixed>): ?array<string, mixed>  $transform
     * @return list<array<string, mixed>>
     */
    private function safeList(mixed $value, callable $transform, int $limit): array
    {
        if (! is_array($value)) {
            return [];
        }

        $result = [];

        foreach (array_slice($value, 0, $limit) as $item) {
            if (! is_array($item)) {
                continue;
            }

            $transformed = $transform($item);

            if ($transformed !== null) {
                $result[] = $transformed;
            }
        }

        return $result;
    }

    private function safeString(
        mixed $value,
        ?string $default,
        int $maximumLength,
    ): ?string {
        if (! is_string($value)) {
            return $default;
        }

        $value = trim(strip_tags($value));

        return $value === '' ? $default : mb_substr($value, 0, $maximumLength);
    }

    private function safeHref(mixed $value): string
    {
        if (! is_string($value)) {
            return '/catalog';
        }

        $value = trim($value);

        if (str_starts_with($value, '/') && ! str_starts_with($value, '//')) {
            return mb_substr($value, 0, 2048);
        }

        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true)
            ? mb_substr($value, 0, 2048)
            : '/catalog';
    }
}
