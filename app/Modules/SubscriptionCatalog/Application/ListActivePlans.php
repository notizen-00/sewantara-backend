<?php

namespace App\Modules\SubscriptionCatalog\Application;

use Illuminate\Support\Collection;

class ListActivePlans
{
    public function execute(?string $billingInterval = null, ?string $currency = null): Collection
    {
        $planModel = config('laravel-subscriptions.models.plan');

        return $planModel::query()
            ->with(['features' => fn ($query) => $query->orderBy('sort_order')])
            ->where('is_active', true)
            ->when($billingInterval, fn ($query, string $interval) => $query->where('invoice_interval', $interval))
            ->when($currency, fn ($query, string $value) => $query->where('currency', strtoupper($value)))
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($plan) => [
                'id' => $plan->getKey(),
                'name' => $plan->name,
                'slug' => $plan->slug,
                'description' => $plan->description,
                'price' => number_format((float) $plan->price, 2, '.', ''),
                'signup_fee' => number_format((float) $plan->signup_fee, 2, '.', ''),
                'currency' => $plan->currency,
                'invoice_period' => $plan->invoice_period,
                'invoice_interval' => $plan->invoice_interval,
                'trial_period' => $plan->trial_period,
                'trial_interval' => $plan->trial_interval,
                'features' => $plan->features->map(fn ($feature) => [
                    'slug' => $feature->slug,
                    'value' => $feature->value,
                ])->values(),
            ]);
    }
}
