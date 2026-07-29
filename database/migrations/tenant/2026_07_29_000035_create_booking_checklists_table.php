<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('booking_checklists', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('tenant_id')->index();
            $table->foreignUuid('booking_id')->constrained()->restrictOnDelete();
            $table->string('type', 30);
            $table->foreignUuid('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestampsTz();
        });
    }
    public function down(): void { Schema::dropIfExists('booking_checklists'); }
};
