<?php

namespace Database\Seeders;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Laravelcm\Subscriptions\Interval;

class PlanSeeder extends Seeder
{
    /**
     * @return array<int, array{
     *     slug: string,
     *     name: array{en: string, id: string},
     *     description: array{en: string, id: string},
     *     price: string,
     *     features: array<int, array{
     *         slug: string,
     *         name: array{en: string, id: string},
     *         value: string,
     *         sort_order: int
     *     }>
     * }>
     */
    public static function definitions(): array
    {
        return [
            [
                'slug' => 'starter',
                'name' => ['en' => 'Starter', 'id' => 'Pemula'],
                'description' => [
                    'en' => 'For early-stage rental businesses.',
                    'id' => 'Untuk usaha penyewaan yang baru memulai.',
                ],
                'price' => '199000.00',
                'features' => [
                    self::limitFeature('branches.limit', 'Branches Limit', 'Batas Cabang', '1', 1),
                    self::limitFeature('users.limit', 'Users Limit', 'Batas Pengguna', '3', 2),
                    self::limitFeature('products.limit', 'Products Limit', 'Batas Produk', '100', 3),
                ],
            ],
            [
                'slug' => 'growth',
                'name' => ['en' => 'Growth', 'id' => 'Berkembang'],
                'description' => [
                    'en' => 'For growing rental businesses.',
                    'id' => 'Untuk usaha penyewaan yang sedang berkembang.',
                ],
                'price' => '499000.00',
                'features' => [
                    self::limitFeature('branches.limit', 'Branches Limit', 'Batas Cabang', '3', 1),
                    self::limitFeature('users.limit', 'Users Limit', 'Batas Pengguna', '10', 2),
                    self::limitFeature('products.limit', 'Products Limit', 'Batas Produk', '1000', 3),
                ],
            ],
            [
                'slug' => 'scale',
                'name' => ['en' => 'Scale', 'id' => 'Skala Besar'],
                'description' => [
                    'en' => 'For large-scale rental operations.',
                    'id' => 'Untuk operasional penyewaan berskala besar.',
                ],
                'price' => '999000.00',
                'features' => [
                    self::limitFeature('branches.limit', 'Branches Limit', 'Batas Cabang', '10', 1),
                    self::limitFeature('users.limit', 'Users Limit', 'Batas Pengguna', '50', 2),
                    self::limitFeature('products.limit', 'Products Limit', 'Batas Produk', '5000', 3),
                ],
            ],
        ];
    }

    /**
     * @return array{
     *     is_active: bool,
     *     signup_fee: string,
     *     currency: string,
     *     trial_period: int,
     *     trial_interval: string,
     *     invoice_period: int,
     *     invoice_interval: string,
     *     grace_period: int,
     *     grace_interval: string
     * }
     */
    public static function billingDefaults(): array
    {
        return [
            'is_active' => true,
            'signup_fee' => '0.00',
            'currency' => 'IDR',
            'trial_period' => 14,
            'trial_interval' => Interval::DAY->value,
            'invoice_period' => 1,
            'invoice_interval' => Interval::MONTH->value,
            'grace_period' => 3,
            'grace_interval' => Interval::DAY->value,
        ];
    }

    public function run(): void
    {
        $planModel = config('laravel-subscriptions.models.plan');
        $featureModel = config('laravel-subscriptions.models.feature');

        DB::transaction(function () use ($planModel, $featureModel): void {
            foreach (self::definitions() as $sortOrder => $definition) {
                $features = $definition['features'];
                unset($definition['features']);

                /** @var Model $plan */
                $plan = $planModel::withTrashed()->firstOrNew([
                    'slug' => $definition['slug'],
                ]);

                $plan->fill([
                    ...$definition,
                    ...self::billingDefaults(),
                    'sort_order' => $sortOrder + 1,
                ])->save();

                if ($plan->trashed()) {
                    $plan->restore();
                }

                foreach ($features as $featureDefinition) {
                    /** @var Model $feature */
                    $feature = $featureModel::withTrashed()->firstOrNew([
                        'plan_id' => $plan->getKey(),
                        'slug' => $featureDefinition['slug'],
                    ]);

                    $feature->fill([
                        ...$featureDefinition,
                        'resettable_period' => 0,
                        'resettable_interval' => Interval::MONTH->value,
                    ])->save();

                    if ($feature->trashed()) {
                        $feature->restore();
                    }
                }
            }
        });
    }

    /**
     * @return array{
     *     slug: string,
     *     name: array{en: string, id: string},
     *     value: string,
     *     sort_order: int
     * }
     */
    private static function limitFeature(
        string $slug,
        string $englishName,
        string $indonesianName,
        string $value,
        int $sortOrder,
    ): array {
        return [
            'slug' => $slug,
            'name' => [
                'en' => $englishName,
                'id' => $indonesianName,
            ],
            'value' => $value,
            'sort_order' => $sortOrder,
        ];
    }
}
