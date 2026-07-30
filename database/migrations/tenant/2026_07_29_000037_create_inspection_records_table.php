<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inspection_records', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id')->index();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_unit_id')->constrained()->restrictOnDelete();
            $table->string('type', 30);
            $table->string('previous_condition', 30)->nullable();
            $table->string('current_condition', 30);
            $table->unsignedBigInteger('meter_value')->nullable();
            $table->decimal('fuel_level', 5, 2)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('inspected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('inspected_at');
            $table->timestampTz('created_at')->useCurrent();
        });
        DB::statement('ALTER TABLE inspection_records ADD CONSTRAINT inspection_fuel_level_valid CHECK (fuel_level IS NULL OR (fuel_level >= 0 AND fuel_level <= 100))');
    }

    public function down(): void
    {
        Schema::dropIfExists('inspection_records');
    }
};
