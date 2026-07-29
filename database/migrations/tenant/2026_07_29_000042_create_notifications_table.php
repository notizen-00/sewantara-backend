<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('tenant_id')->nullable()->index();
            $table->uuidMorphs('notifiable');
            $table->string('type');
            $table->jsonb('data');
            $table->timestampTz('read_at')->nullable();
            $table->timestampsTz();
        });
    }
    public function down(): void { Schema::dropIfExists('notifications'); }
};
