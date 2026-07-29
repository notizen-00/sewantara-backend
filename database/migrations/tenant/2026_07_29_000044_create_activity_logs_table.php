<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('tenant_id')->nullable()->index();
            $table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event', 100);
            $table->string('subject_type', 150)->nullable();
            $table->uuid('subject_id')->nullable();
            $table->text('description')->nullable();
            $table->jsonb('properties')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['subject_type', 'subject_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('activity_logs'); }
};
