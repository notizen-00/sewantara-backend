<?php

namespace App\Modules\SubscriptionBilling\Application;

use App\Models\SubscriptionPayment;
use Carbon\Carbon;
use Laravelcm\Subscriptions\Models\Subscription;
use Laravelcm\Subscriptions\Services\Period;

class ActivateSubscriptionForPaidPayment
{
    public function execute(
        SubscriptionPayment $payment,
        bool $extendActive = true,
    ): ?Subscription {
        $subscriptionClass = config('laravel-subscriptions.models.subscription');
        /** @var Subscription $subscriptionModel */
        $subscriptionModel = new $subscriptionClass;
        $subscriptionModel->setConnection($payment->getConnectionName());

        /** @var Subscription $subscription */
        $subscription = $subscriptionModel->newQuery()
            ->whereKey($payment->plan_subscription_id)
            ->where('subscriber_id', $payment->tenant_id)
            ->lockForUpdate()
            ->firstOrFail();
        $subscription->loadMissing('plan');

        if (! $extendActive
            && ! $subscription->onTrial()
            && ! $subscription->inactive()
            && ! $subscription->canceled()) {
            return null;
        }

        $now = Carbon::now();

        if ($subscription->onTrial()) {
            $subscription->forceFill([
                'trial_ends_at' => null,
                'starts_at' => $now,
                'canceled_at' => null,
            ])->save();

            return $subscription->refresh();
        }

        $wasInactive = $subscription->inactive();
        $periodStart = $wasInactive || $subscription->ends_at === null
            ? $now
            : Carbon::instance($subscription->ends_at);
        $period = new Period(
            interval: $subscription->plan->invoice_interval,
            count: $subscription->plan->invoice_period,
            start: $periodStart,
        );

        if ($wasInactive) {
            $subscription->usage()->delete();
        }

        $subscription->forceFill([
            'trial_ends_at' => null,
            'starts_at' => $wasInactive ? $period->getStartDate() : $subscription->starts_at,
            'ends_at' => $period->getEndDate(),
            'canceled_at' => null,
        ])->save();

        return $subscription->refresh();
    }
}
