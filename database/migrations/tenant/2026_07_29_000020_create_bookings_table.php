<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('tenant_id')->index();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('booking_number', 100);
            $table->timestampTz('start_at');
            $table->timestampTz('end_at');
            $table->timestampTz('actual_start_at')->nullable();
            $table->timestampTz('actual_end_at')->nullable();
            $table->string('status', 30)->default('draft');
            $table->string('fulfillment_type', 30);
            foreach (['subtotal', 'discount_amount', 'tax_amount', 'delivery_fee', 'deposit_amount', 'charge_amount', 'total_amount', 'paid_amount', 'remaining_amount'] as $column) {
                $table->decimal($column, 18, 2)->default(0);
            }
            $table->string('payment_status', 30)->default('unpaid');
            $table->text('customer_notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->timestampTz('confirmed_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
            $table->unique(['tenant_id', 'booking_number']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'start_at', 'end_at']);
            $table->index(['tenant_id', 'customer_id']);
            $table->index(['tenant_id', 'branch_id', 'start_at', 'end_at']);
        });
        DB::statement('ALTER TABLE bookings ADD CONSTRAINT bookings_period_valid CHECK (start_at < end_at)');
        DB::statement('ALTER TABLE bookings ADD CONSTRAINT bookings_amounts_nonnegative CHECK (subtotal >= 0 AND discount_amount >= 0 AND tax_amount >= 0 AND delivery_fee >= 0 AND deposit_amount >= 0 AND charge_amount >= 0 AND total_amount >= 0 AND paid_amount >= 0 AND remaining_amount >= 0)');
    }
    public function down(): void { Schema::dropIfExists('bookings'); }
};
