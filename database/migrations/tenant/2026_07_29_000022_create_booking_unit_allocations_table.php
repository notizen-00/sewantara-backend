<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_unit_allocations', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id')->index();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_unit_id')->constrained()->restrictOnDelete();
            $table->timestampTz('start_at');
            $table->timestampTz('end_at');
            $table->string('status', 30)->default('reserved');
            $table->timestampTz('allocated_at');
            $table->timestampTz('checked_out_at')->nullable();
            $table->timestampTz('returned_at')->nullable();
            $table->timestampsTz();
            $table->index(['tenant_id', 'product_unit_id', 'start_at', 'end_at'], 'allocations_unit_period');
            $table->index(['tenant_id', 'booking_id'], 'allocations_booking');
            $table->index(['tenant_id', 'booking_item_id'], 'allocations_item');
            $table->index(['tenant_id', 'product_unit_id', 'status'], 'allocations_active_status');
        });
        DB::statement('ALTER TABLE booking_unit_allocations ADD CONSTRAINT allocation_period_valid CHECK (start_at < end_at)');
        DB::statement("CREATE INDEX booking_allocations_active_idx ON booking_unit_allocations (tenant_id, product_unit_id, start_at, end_at) WHERE status IN ('reserved', 'checked_out')");
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_unit_allocations');
    }
};
