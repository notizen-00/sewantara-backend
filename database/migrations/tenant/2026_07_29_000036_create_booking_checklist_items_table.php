<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('booking_checklist_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('tenant_id')->index();
            $table->foreignUuid('booking_checklist_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_unit_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 200);
            $table->string('type', 30);
            $table->text('expected_value')->nullable();
            $table->text('actual_value')->nullable();
            $table->boolean('is_passed')->nullable();
            $table->text('notes')->nullable();
            $table->timestampsTz();
        });
    }
    public function down(): void { Schema::dropIfExists('booking_checklist_items'); }
};
