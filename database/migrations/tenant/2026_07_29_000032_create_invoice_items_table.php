<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_items', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id')->index();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('description', 255);
            $table->decimal('quantity', 12, 2);
            $table->decimal('unit_amount', 18, 2);
            $table->decimal('total_amount', 18, 2);
            $table->timestampsTz();
        });
        DB::statement('ALTER TABLE invoice_items ADD CONSTRAINT invoice_items_values_valid CHECK (quantity > 0 AND unit_amount >= 0 AND total_amount >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
