<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_images', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('tenant_id')->index();
            $table->foreignUuid('product_id')->constrained()->cascadeOnDelete();
            $table->string('image_path');
            $table->string('alt_text')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestampsTz();
        });
        DB::statement('CREATE UNIQUE INDEX product_images_one_primary_per_product ON product_images (product_id) WHERE is_primary = true');
    }
    public function down(): void { Schema::dropIfExists('product_images'); }
};
