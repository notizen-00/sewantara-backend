<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('subscription_payments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('tenant_id')->index();
            $table->foreignId('plan_subscription_id')
                ->constrained(config('laravel-subscriptions.tables.subscriptions'))
                ->restrictOnDelete();
            $table->string('payment_number', 100);
            $table->string('gateway', 50)->nullable();
            $table->string('gateway_reference')->nullable();
            $table->decimal('amount', 18, 2);
            $table->char('currency', 3)->default('IDR');
            $table->string('status', 30)->index();
            $table->timestampTz('paid_at')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();
            $table->unique(['tenant_id', 'payment_number']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
        });
    }
    public function down(): void { Schema::dropIfExists('subscription_payments'); }
};
