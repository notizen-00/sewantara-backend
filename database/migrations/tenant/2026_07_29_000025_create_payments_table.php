<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('tenant_id')->index();
            $table->foreignUuid('booking_id')->constrained()->restrictOnDelete();
            $table->string('payment_number', 100);
            $table->string('type', 30);
            $table->string('method', 30);
            $table->decimal('amount', 18, 2);
            $table->string('status', 30)->default('pending');
            $table->string('gateway', 50)->nullable();
            $table->string('gateway_reference')->nullable();
            $table->string('proof_path')->nullable();
            $table->timestampTz('paid_at')->nullable();
            $table->timestampTz('expired_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->unique(['tenant_id', 'payment_number']);
            $table->index(['tenant_id', 'booking_id']);
            $table->index(['tenant_id', 'status']);
            $table->index(['gateway', 'gateway_reference']);
        });
        DB::statement('ALTER TABLE payments ADD CONSTRAINT payments_amount_nonnegative CHECK (amount >= 0)');
    }
    public function down(): void { Schema::dropIfExists('payments'); }
};
