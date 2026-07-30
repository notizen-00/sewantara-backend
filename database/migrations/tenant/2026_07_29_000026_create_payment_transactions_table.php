<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_transactions', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id')->index();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->string('gateway', 50);
            $table->string('transaction_id')->nullable();
            $table->jsonb('request_payload')->nullable();
            $table->jsonb('response_payload')->nullable();
            $table->jsonb('callback_payload')->nullable();
            $table->boolean('signature_valid')->nullable();
            $table->timestampsTz();
            $table->index(['gateway', 'transaction_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};
