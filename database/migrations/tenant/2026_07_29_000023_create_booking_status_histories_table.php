<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('booking_status_histories', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('tenant_id')->index();
            $table->foreignUuid('booking_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->text('notes')->nullable();
            $table->foreignUuid('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('created_at')->useCurrent();
        });
    }
    public function down(): void { Schema::dropIfExists('booking_status_histories'); }
};
