<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_prices', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('tenant_id')->index();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('pricing_type', 30);
            $table->integer('duration');
            $table->decimal('price', 18, 2);
            $table->timestampTz('start_at')->nullable();
            $table->timestampTz('end_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });
        DB::statement('ALTER TABLE product_prices ADD CONSTRAINT product_prices_valid CHECK (duration > 0 AND price >= 0 AND (end_at IS NULL OR start_at IS NULL OR start_at < end_at))');
    }
    public function down(): void { Schema::dropIfExists('product_prices'); }
};
