<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memberships', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id')->index();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('membership_number', 100);
            $table->string('status', 30)->default('pending');
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->decimal('price_amount', 18, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
            $table->unique(['tenant_id', 'membership_number']);
            $table->index(['tenant_id', 'status']);
        });
        DB::statement('ALTER TABLE memberships ADD CONSTRAINT memberships_price_amount_nonnegative CHECK (price_amount >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('memberships');
    }
};
