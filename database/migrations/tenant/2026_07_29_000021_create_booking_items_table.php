<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('booking_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('tenant_id')->index();
            $table->foreignUuid('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('product_name', 200);
            $table->string('sku', 100)->nullable();
            $table->string('inventory_type', 30);
            $table->string('pricing_type', 30);
            $table->integer('quantity');
            $table->integer('duration');
            foreach (['unit_price', 'subtotal', 'discount_amount', 'deposit_amount', 'total_amount'] as $column) {
                $table->decimal($column, 18, 2)->default(0);
            }
            $table->text('notes')->nullable();
            $table->timestampsTz();
        });
        DB::statement('ALTER TABLE booking_items ADD CONSTRAINT booking_items_values_valid CHECK (quantity > 0 AND duration > 0 AND unit_price >= 0 AND subtotal >= 0 AND discount_amount >= 0 AND deposit_amount >= 0 AND total_amount >= 0)');
    }
    public function down(): void { Schema::dropIfExists('booking_items'); }
};
