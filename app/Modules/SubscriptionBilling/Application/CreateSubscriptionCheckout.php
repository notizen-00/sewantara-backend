<?php

namespace App\Modules\SubscriptionBilling\Application;

use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use App\Models\TenantEngine;
use App\Modules\SubscriptionBilling\Application\Data\CheckoutSession;
use App\Modules\SubscriptionBilling\Contracts\SubscriptionPaymentGateway;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class CreateSubscriptionCheckout
{
    public function __construct(
        private readonly SubscriptionPaymentGateway $gateway,
    ) {}

    /**
     * @param  array{name?: string|null, email?: string|null}  $customer
     * @return array{payment: SubscriptionPayment, checkout: CheckoutSession}
     */
    public function execute(Tenant $tenant, array $customer): array
    {
        $subscription = $tenant->planSubscription('main');

        if ($subscription === null) {
            throw ValidationException::withMessages([
                'subscription' => ['Langganan utama belum tersedia.'],
            ]);
        }

        $subscription->loadMissing('plan');
        $plan = $subscription->plan;

        if ($plan === null || ! $plan->is_active) {
            throw ValidationException::withMessages([
                'subscription' => ['Paket langganan tidak tersedia.'],
            ]);
        }

        $planAmount = (int) round((float) $plan->price);

        $paidEngines = TenantEngine::query()
            ->where('tenant_id', (string) $tenant->getKey())
            ->where('is_enabled', true)
            ->where('price_snapshot', '>', 0)
            ->with('engine')
            ->get();

        $engineItems = $paidEngines->map(fn (TenantEngine $tenantEngine): array => [
            'id' => 'engine-'.$tenantEngine->engine->code,
            'price' => (int) round((float) $tenantEngine->price_snapshot),
            'quantity' => 1,
            'name' => 'Engine: '.$tenantEngine->engine->name,
        ])->values();

        $engineTotal = (int) $engineItems->sum('price');
        $amount = $planAmount + $engineTotal;

        if ($amount < 1) {
            throw ValidationException::withMessages([
                'subscription' => ['Paket gratis tidak memerlukan checkout pembayaran.'],
            ]);
        }

        $paymentNumber = 'SUB-'.Str::ulid();

        $connection = (new SubscriptionPayment)->getConnection();
        $payment = $connection->transaction(fn (): SubscriptionPayment => SubscriptionPayment::query()->create([
            'tenant_id' => (string) $tenant->getKey(),
            'plan_subscription_id' => $subscription->getKey(),
            'payment_number' => $paymentNumber,
            'gateway' => (string) config('subscription-billing.default', 'xendit'),
            'amount' => $amount,
            'currency' => strtoupper((string) $plan->currency),
            'status' => 'pending',
            'metadata' => [
                'plan' => [
                    'id' => $plan->getKey(),
                    'slug' => $plan->slug,
                    'name' => $plan->name,
                    'invoice_period' => $plan->invoice_period,
                    'invoice_interval' => $plan->invoice_interval,
                ],
                'engines' => $paidEngines->map(fn (TenantEngine $tenantEngine): array => [
                    'code' => $tenantEngine->engine->code,
                    'price' => (string) $tenantEngine->price_snapshot,
                ])->values()->all(),
            ],
        ]));

        try {
            $checkout = $this->gateway->createCheckout(
                orderId: $paymentNumber,
                grossAmount: $amount,
                customer: $customer,
                items: [
                    [
                        'id' => (string) $plan->slug,
                        'price' => $planAmount,
                        'quantity' => 1,
                        'name' => 'Sewantara '.$plan->name,
                    ],
                    ...$engineItems->all(),
                ],
            );
        } catch (Throwable $exception) {
            $payment->forceFill(['status' => 'failed'])->save();

            throw $exception;
        }

        $payment->forceFill([
            'gateway_reference' => $checkout->token,
            'metadata' => array_merge($payment->metadata ?? [], [
                'checkout' => [
                    'token' => $checkout->token,
                    'redirect_url' => $checkout->redirectUrl,
                ],
            ]),
        ])->save();

        return compact('payment', 'checkout');
    }
}
