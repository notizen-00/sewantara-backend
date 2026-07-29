<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tenant_settings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('tenant_id')->index();
            $table->string('group', 100);
            $table->string('key', 150);
            $table->jsonb('value');
            $table->timestampsTz();
            $table->unique(['tenant_id', 'group', 'key']);
        });
    }
    public function down(): void { Schema::dropIfExists('tenant_settings'); }
};
