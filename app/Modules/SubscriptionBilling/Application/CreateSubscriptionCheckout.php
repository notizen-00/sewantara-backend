<?php

namespace App\Modules\SubscriptionBilling\Application;

use App\Models\SubscriptionPayment;
use App\Models\Tenant;
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

        $amount = (int) round((float) $plan->price);

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
            ],
        ]));

        try {
            $checkout = $this->gateway->createCheckout(
                orderId: $paymentNumber,
                grossAmount: $amount,
                customer: $customer,
                items: [[
                    'id' => (string) $plan->slug,
                    'price' => $amount,
                    'quantity' => 1,
                    'name' => 'Sewantara '.$plan->name,
                ]],
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
