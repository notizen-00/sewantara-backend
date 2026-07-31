<?php

namespace App\Modules\SubscriptionCatalog\Application;

use App\Models\Tenant;
use Laravelcm\Subscriptions\Models\Subscription;

class GetCurrentTenantSubscription
{
    /**
     * @return array<string, mixed>|null
     */
    public function execute(Tenant $tenant): ?array
    {
        $subscription = $tenant->planSubscription('main');

        if ($subscription === null) {
            return null;
        }

        $subscription->loadMissing(['plan.features']);
        $plan = $subscription->plan;

        return [
            'id' => $subscription->getKey(),
            'name' => $subscription->name,
            'slug' => $subscription->slug,
            'status' => $this->status($subscription),
            'is_active' => $subscription->active(),
            'is_on_trial' => $subscription->onTrial(),
            'is_canceled' => $subscription->canceled(),
            'trial_ends_at' => $subscription->trial_ends_at?->toIso8601String(),
            'starts_at' => $subscription->starts_at?->toIso8601String(),
            'ends_at' => $subscription->ends_at?->toIso8601String(),
            'canceled_at' => $subscription->canceled_at?->toIso8601String(),
            'plan' => $plan === null ? null : [
                'id' => $plan->getKey(),
                'name' => $plan->name,
                'slug' => $plan->slug,
                'description' => $plan->description,
                'price' => number_format((float) $plan->price, 2, '.', ''),
                'signup_fee' => number_format((float) $plan->signup_fee, 2, '.', ''),
                'currency' => $plan->currency,
                'invoice_period' => $plan->invoice_period,
                'invoice_interval' => $plan->invoice_interval,
                'features' => $plan->features
                    ->sortBy('sort_order')
                    ->map(fn ($feature) => [
                        'slug' => $feature->slug,
                        'name' => $feature->name,
                        'value' => $feature->value,
                    ])
                    ->values(),
            ],
        ];
    }

    private function status(Subscription $subscription): string
    {
        if ($subscription->onTrial()) {
            return 'trial';
        }

        if ($subscription->canceled()) {
            return 'canceled';
        }

        return $subscription->active() ? 'active' : 'expired';
    }
}
