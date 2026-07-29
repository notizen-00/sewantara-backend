<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('deposit_transactions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('tenant_id')->index();
            $table->foreignUuid('deposit_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30);
            $table->decimal('amount', 18, 2);
            $table->text('reason')->nullable();
            $table->string('reference_type', 100)->nullable();
            $table->uuid('reference_id')->nullable();
            $table->foreignUuid('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['reference_type', 'reference_id']);
        });
        DB::statement('ALTER TABLE deposit_transactions ADD CONSTRAINT deposit_transaction_amount_nonnegative CHECK (amount >= 0)');
    }
    public function down(): void { Schema::dropIfExists('deposit_transactions'); }
};
