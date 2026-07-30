<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 100)->unique();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->string('icon', 100)->nullable();
            $table->jsonb('configuration');
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestampsTz();
            $table->softDeletesTz();
            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_templates');
    }
};
