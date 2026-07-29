<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('inventory_stocks', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id')->index();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity_total')->default(0);
            $table->unsignedInteger('quantity_reserved')->default(0);
            $table->unsignedInteger('quantity_rented')->default(0);
            $table->unsignedInteger('quantity_maintenance')->default(0);
            $table->unsignedInteger('quantity_damaged')->default(0);
            $table->unsignedInteger('quantity_lost')->default(0);
            $table->timestampsTz();
            $table->unique(['tenant_id', 'product_id', 'branch_id']);
        });
        DB::statement('ALTER TABLE inventory_stocks ADD CONSTRAINT inventory_stock_totals_valid CHECK (quantity_reserved + quantity_rented + quantity_maintenance + quantity_damaged + quantity_lost <= quantity_total)');
    }
    public function down(): void { Schema::dropIfExists('inventory_stocks'); }
};
