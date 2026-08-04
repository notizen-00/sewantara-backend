<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_orders', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id')->index();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('order_number', 100);
            $table->string('status', 30)->default('draft');
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
            $table->unique(['tenant_id', 'order_number']);
            $table->index(['tenant_id', 'status']);
        });
        DB::statement('ALTER TABLE sales_orders ADD CONSTRAINT sales_orders_total_amount_nonnegative CHECK (total_amount >= 0)');

        Schema::create('sales_order_items', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id')->index();
            $table->foreignId('sales_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('product_name', 200);
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 18, 2)->default(0);
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->timestampsTz();
        });
        DB::statement('ALTER TABLE sales_order_items ADD CONSTRAINT sales_order_items_values_valid CHECK (quantity > 0 AND unit_price >= 0 AND subtotal >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_order_items');
        Schema::dropIfExists('sales_orders');
    }
};
