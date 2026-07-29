<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('tenant_id')->nullable()->index();
            $table->string('name', 100);
            $table->string('code', 100);
            $table->boolean('is_system')->default(false);
            $table->timestampsTz();
            $table->unique(['tenant_id', 'code']);
        });
    }
    public function down(): void { Schema::dropIfExists('roles'); }
};
