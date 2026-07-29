<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('tenant_id')->index();
            $table->foreignUuid('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 200);
            $table->string('slug', 200);
            $table->string('sku', 100)->nullable();
            $table->string('brand', 100)->nullable();
            $table->string('model', 100)->nullable();
            $table->text('description')->nullable();
            $table->string('inventory_type', 30);
            $table->string('default_pricing_type', 30);
            $table->integer('minimum_rental_duration')->default(1);
            $table->decimal('deposit_amount', 18, 2)->default(0);
            $table->decimal('late_fee_amount', 18, 2)->default(0);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
            $table->softDeletesTz();
            $table->unique(['tenant_id', 'slug']);
            $table->unique(['tenant_id', 'sku']);
            $table->index(['tenant_id', 'category_id']);
            $table->index(['tenant_id', 'is_active']);
        });
        DB::statement('ALTER TABLE products ADD CONSTRAINT products_amounts_nonnegative CHECK (deposit_amount >= 0 AND late_fee_amount >= 0)');
    }
    public function down(): void { Schema::dropIfExists('products'); }
};
