<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_webhook_routes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('tenant_id');
            $table->string('provider', 50);
            $table->string('external_reference', 150);
            $table->uuid('payment_public_id');
            $table->decimal('expected_amount', 18, 2);
            $table->char('currency', 3)->default('IDR');
            $table->string('status', 30)->default('active')->index();
            $table->timestampTz('expires_at')->nullable()->index();
            $table->timestampsTz();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['provider', 'external_reference']);
            $table->index(['tenant_id', 'payment_public_id']);
        });

        Schema::create('payment_webhook_events', function (Blueprint $table): void {
            $table->id();
            $table->string('provider', 50);
            $table->string('provider_event_id', 200);
            $table->string('provider_transaction_id', 200)->nullable()->index();
            $table->string('tenant_id')->nullable()->index();
            $table->string('external_reference', 150)->nullable();
            $table->string('payload_hash', 64);
            $table->jsonb('redacted_payload')->nullable();
            $table->string('status', 30)->default('processing')->index();
            $table->string('error_code', 100)->nullable();
            $table->unsignedInteger('attempt_count')->default(1);
            $table->timestampTz('verified_at');
            $table->timestampTz('last_attempt_at');
            $table->timestampTz('processed_at')->nullable();
            $table->timestampsTz();
            $table->unique(['provider', 'provider_event_id']);
            $table->index(['provider', 'external_reference']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_webhook_events');
        Schema::dropIfExists('payment_webhook_routes');
    }
};
