<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_movements', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('tenant_id')->index();
            $table->foreignUuid('product_unit_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 50);
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30)->nullable();
            $table->foreignUuid('from_branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignUuid('to_branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestampTz('occurred_at');
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['tenant_id', 'product_unit_id', 'occurred_at']);
        });
    }
    public function down(): void { Schema::dropIfExists('product_movements'); }
};
