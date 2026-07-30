<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_transfers', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id')->index();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('from_branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('to_branch_id')->constrained('branches')->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('occurred_at');
            $table->timestampsTz();
            $table->index(['tenant_id', 'product_id', 'occurred_at']);
        });

        DB::statement(
            'ALTER TABLE inventory_transfers
             ADD CONSTRAINT inventory_transfers_quantity_positive CHECK (quantity > 0)',
        );
        DB::statement(
            'ALTER TABLE inventory_transfers
             ADD CONSTRAINT inventory_transfers_branches_different
             CHECK (from_branch_id <> to_branch_id)',
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_transfers');
    }
};
