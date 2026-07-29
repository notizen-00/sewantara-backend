<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('tenant_id')->index();
            $table->string('name', 150);
            $table->string('code', 50);
            $table->string('email', 150)->nullable();
            $table->string('phone', 30)->nullable();
            $table->text('address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
            $table->softDeletesTz();
            $table->unique(['tenant_id', 'code']);
        });
    }
    public function down(): void { Schema::dropIfExists('branches'); }
};
