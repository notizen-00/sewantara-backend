<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('deposits', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('tenant_id')->index();
            $table->foreignUuid('booking_id')->constrained()->restrictOnDelete();
            foreach (['amount', 'deducted_amount', 'refunded_amount', 'remaining_amount'] as $column) {
                $table->decimal($column, 18, 2)->default(0);
            }
            $table->string('status', 30)->default('pending');
            $table->timestampTz('held_at')->nullable();
            $table->timestampTz('refunded_at')->nullable();
            $table->timestampsTz();
            $table->unique(['tenant_id', 'booking_id']);
        });
        DB::statement('ALTER TABLE deposits ADD CONSTRAINT deposits_amounts_nonnegative CHECK (amount >= 0 AND deducted_amount >= 0 AND refunded_amount >= 0 AND remaining_amount >= 0)');
    }
    public function down(): void { Schema::dropIfExists('deposits'); }
};
