<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id')->index();
            $table->foreignId('booking_id')->constrained()->restrictOnDelete();
            $table->string('invoice_number', 100);
            $table->date('issue_date');
            $table->date('due_date')->nullable();
            foreach (['subtotal', 'discount_amount', 'tax_amount', 'total_amount', 'paid_amount', 'remaining_amount'] as $column) {
                $table->decimal($column, 18, 2)->default(0);
            }
            $table->string('status', 30)->default('draft');
            $table->string('pdf_path')->nullable();
            $table->timestampsTz();
            $table->unique(['tenant_id', 'invoice_number']);
        });
        DB::statement('ALTER TABLE invoices ADD CONSTRAINT invoices_amounts_nonnegative CHECK (subtotal >= 0 AND discount_amount >= 0 AND tax_amount >= 0 AND total_amount >= 0 AND paid_amount >= 0 AND remaining_amount >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
