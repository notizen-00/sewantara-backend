<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('booking_charges', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('tenant_id')->index();
            $table->foreignUuid('booking_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('booking_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 50);
            $table->text('description');
            $table->decimal('quantity', 12, 2);
            $table->decimal('unit_amount', 18, 2);
            $table->decimal('total_amount', 18, 2);
            $table->boolean('is_deducted_from_deposit')->default(false);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
        });
        DB::statement('ALTER TABLE booking_charges ADD CONSTRAINT booking_charges_values_valid CHECK (quantity > 0 AND unit_amount >= 0 AND total_amount >= 0)');
    }
    public function down(): void { Schema::dropIfExists('booking_charges'); }
};
