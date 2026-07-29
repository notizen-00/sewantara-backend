<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('tenant_id')->index();
            $table->foreignUuid('payment_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignUuid('deposit_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('refund_number', 100);
            $table->decimal('amount', 18, 2);
            $table->string('status', 30)->default('pending');
            $table->string('gateway_reference')->nullable();
            $table->text('reason')->nullable();
            $table->foreignUuid('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('refunded_at')->nullable();
            $table->timestampsTz();
            $table->unique(['tenant_id', 'refund_number']);
        });
        DB::statement('ALTER TABLE refunds ADD CONSTRAINT refund_source_required CHECK (payment_id IS NOT NULL OR deposit_id IS NOT NULL)');
        DB::statement('ALTER TABLE refunds ADD CONSTRAINT refund_amount_nonnegative CHECK (amount >= 0)');
    }
    public function down(): void { Schema::dropIfExists('refunds'); }
};
