<?php

namespace App\Modules\PublicApi\Application\Home;

use App\Modules\PublicApi\Application\GetPublicTenantProfile;
use App\Modules\PublicApi\Domain\Home\Contracts\PublicHomeRepositoryContract;
use App\Modules\PublicApi\DTO\Home\PublicHomeData;
use Illuminate\Http\Request;

class GetPublicHome
{
    public function __construct(
        private readonly PublicHomeRepositoryContract $repository,
        private readonly GetPublicTenantProfile $tenantProfile,
    ) {}

    public function execute(Request $request): PublicHomeData
    {
        $tenant = $this->tenantProfile->execute($request);
        $homepage = $this->repository->settings('homepage');
        $public = $this->repository->settings('public');

        return new PublicHomeData(
            tenant: $tenant,

            hero: $this->hero($tenant, $homepage),

            categories: $this->repository->categories(),

            featuredProducts: $this->repository->featuredProducts(),

            promotion: $this->promotion($homepage),

            howToBook: $this->howToBook($homepage),

            testimonials: $this->testimonials($homepage),

            faqs: $this->faqs($homepage),

            articles: $this->repository->latestArticles(),

            stats: [
                'products' => $this->repository->productCount(),
                'bookings' => $this->repository->bookingCount(),
                'customers' => $this->repository->customerCount(),
                'averageRating' => $this->repository->averageRating(),
            ],

            cta: $this->cta($tenant, $homepage, $public),
        );
    }

    private function hero(array $tenant, array $homepage): array
    {
        $businessName = (string) (
            $tenant['businessName']
            ?? 'Sewantara'
        );

        $title = (string) (
            $homepage['hero_title']
            ?? "Sewa mudah di {$businessName}"
        );

        return [
            'eyebrow' => (string) (
                $homepage['hero_eyebrow']
                ?? 'Booking online lebih mudah'
            ),

            'title' => $title,

            'description' => (string) (
                $homepage['hero_subtitle']
                ?? $tenant['description']
                ?? ''
            ),

            'primaryAction' => [
                'label' => (string) (
                    $homepage['cta_label']
                    ?? 'Lihat katalog'
                ),

                'href' => (string) (
                    $homepage['cta_url']
                    ?? '/catalog'
                ),
            ],

            'secondaryAction' => [
                'label' => 'Hubungi kami',
                'href' => '/contact',
            ],

            'image' => [
                'url' => (string) (
                    $homepage['hero_image_url']
                    ?? ''
                ),

                'alt' => (string) (
                    $homepage['hero_image_alt']
                    ?? $title
                ),
            ],

            'trustPoints' => $this->stringList(
                $homepage['trust_points'] ?? [
                    'Booking mudah',
                    'Harga transparan',
                    'Pembayaran aman',
                ],
            ),
        ];
    }

    private function promotion(array $homepage): ?array
    {
        $promotion = $homepage['promotion'] ?? null;

        if (
            ! is_array($promotion)
            || ! ($promotion['enabled'] ?? false)
        ) {
            return null;
        }

        return [
            'id' => (string) (
                $promotion['id']
                ?? 'homepage-promotion'
            ),

            'title' => (string) (
                $promotion['title']
                ?? ''
            ),

            'description' => (string) (
                $promotion['description']
                ?? ''
            ),

            'badge' => (string) (
                $promotion['badge']
                ?? 'Promo'
            ),

            'couponCode' => $promotion['coupon_code'] ?? null,

            'discountPercent' => isset(
                $promotion['discount_percent'],
            )
                ? (int) $promotion['discount_percent']
                : null,

            'image' => [
                'url' => (string) (
                    $promotion['image_url']
                    ?? ''
                ),

                'alt' => (string) (
                    $promotion['title']
                    ?? 'Promosi'
                ),
            ],

            'action' => [
                'label' => (string) (
                    $promotion['action_label']
                    ?? 'Lihat promo'
                ),

                'href' => (string) (
                    $promotion['action_url']
                    ?? '/catalog'
                ),
            ],

            'startsAt' => $promotion['starts_at'] ?? null,
            'endsAt' => $promotion['ends_at'] ?? null,
        ];
    }

    private function howToBook(array $homepage): array
    {
        $items = $homepage['how_to_book'] ?? [];

        if (! is_array($items) || $items === []) {
            return [
                [
                    'title' => 'Pilih produk',
                    'description' => 'Temukan produk yang ingin disewa.',
                ],
                [
                    'title' => 'Tentukan jadwal',
                    'description' => 'Pilih tanggal, waktu, dan durasi.',
                ],
                [
                    'title' => 'Konfirmasi booking',
                    'description' => 'Isi data dan selesaikan pembayaran.',
                ],
            ];
        }

        return collect($items)
            ->filter(fn($item): bool => is_array($item))
            ->take(10)
            ->values()
            ->map(
                fn(array $item): array => [
                    'title' => (string) (
                        $item['title']
                        ?? ''
                    ),
                    'description' => (string) (
                        $item['description']
                        ?? ''
                    ),
                ],
            )
            ->all();
    }

    private function testimonials(array $homepage): array
    {
        $items = $homepage['testimonials'] ?? [];

        if (! is_array($items)) {
            return [];
        }

        return collect($items)
            ->filter(fn($item): bool => is_array($item))
            ->take(12)
            ->values()
            ->map(
                fn(array $item, int $index): array => [
                    'id' => (string) (
                        $item['id']
                        ?? "testimonial-{$index}"
                    ),

                    'customerName' => (string) (
                        $item['name']
                        ?? ''
                    ),

                    'rating' => min(
                        5,
                        max(
                            1,
                            (int) (
                                $item['rating']
                                ?? 5
                            ),
                        ),
                    ),

                    'quote' => (string) (
                        $item['quote']
                        ?? ''
                    ),

                    'productName' => (string) (
                        $item['product_name']
                        ?? ''
                    ),

                    'createdAt' => (string) (
                        $item['created_at']
                        ?? now()->toIso8601String()
                    ),
                ],
            )
            ->all();
    }

    private function faqs(array $homepage): array
    {
        $items = $homepage['faq'] ?? [];

        if (! is_array($items)) {
            return [];
        }

        return collect($items)
            ->filter(fn($item): bool => is_array($item))
            ->take(30)
            ->values()
            ->map(
                fn(array $item, int $index): array => [
                    'id' => (string) (
                        $item['id']
                        ?? "faq-{$index}"
                    ),

                    'question' => (string) (
                        $item['question']
                        ?? ''
                    ),

                    'answer' => (string) (
                        $item['answer']
                        ?? ''
                    ),
                ],
            )
            ->all();
    }

    private function cta(
        array $tenant,
        array $homepage,
        array $public,
    ): array {
        return [
            'title' => (string) (
                $homepage['bottom_cta_title']
                ?? 'Siap melakukan booking?'
            ),

            'description' => (string) (
                $homepage['bottom_cta_description']
                ?? 'Pilih produk dan jadwal sewa Anda sekarang.'
            ),

            'primaryAction' => [
                'label' => 'Lihat katalog',
                'href' => '/catalog',
            ],

            'secondaryAction' => [
                'label' => 'Hubungi kami',

                'href' => $this->whatsappUrl(
                    (string) (
                        $public['whatsapp']
                        ?? $tenant['contact']['whatsapp']
                        ?? ''
                    ),
                ),

                'external' => true,
            ],
        ];
    }

    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->filter(fn($item): bool => is_string($item))
            ->values()
            ->all();
    }

    private function whatsappUrl(string $number): string
    {
        $normalized = preg_replace('/\D+/', '', $number) ?? '';

        if ($normalized === '') {
            return '/contact';
        }

        if (str_starts_with($normalized, '0')) {
            $normalized = '62' . substr($normalized, 1);
        }

        return "https://wa.me/{$normalized}";
    }
}
