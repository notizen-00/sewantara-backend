<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('inspection_photos', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('tenant_id')->index();
            $table->foreignUuid('inspection_record_id')->constrained()->cascadeOnDelete();
            $table->string('photo_path');
            $table->string('caption')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestampTz('created_at')->useCurrent();
        });
    }
    public function down(): void { Schema::dropIfExists('inspection_photos'); }
};
