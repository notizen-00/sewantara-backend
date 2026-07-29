<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('maintenance_records', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('tenant_id')->index();
            $table->foreignId('product_unit_id')->constrained()->restrictOnDelete();
            $table->string('type', 30);
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->string('vendor', 150)->nullable();
            $table->decimal('cost', 18, 2)->default(0);
            $table->timestampTz('scheduled_at')->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->string('status', 30)->default('scheduled');
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->index(['tenant_id', 'product_unit_id', 'status']);
        });
        DB::statement('ALTER TABLE maintenance_records ADD CONSTRAINT maintenance_cost_nonnegative CHECK (cost >= 0)');
    }
    public function down(): void { Schema::dropIfExists('maintenance_records'); }
};
