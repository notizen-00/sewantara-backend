<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_units', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id')->index();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('unit_code', 100);
            $table->string('barcode', 150)->nullable();
            $table->string('qr_code', 150)->nullable();
            $table->string('serial_number', 150)->nullable();
            $table->string('plate_number', 50)->nullable();
            $table->string('condition', 30)->default('good');
            $table->string('status', 30)->default('available');
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_price', 18, 2)->nullable();
            $table->unsignedBigInteger('current_meter')->nullable();
            $table->string('meter_unit', 30)->nullable();
            $table->text('notes')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
            $table->unique(['tenant_id', 'unit_code']);
            $table->unique(['tenant_id', 'barcode']);
            $table->unique(['tenant_id', 'qr_code']);
            $table->index(['tenant_id', 'product_id']);
            $table->index(['tenant_id', 'branch_id', 'status']);
            $table->index(['tenant_id', 'product_id', 'status']);
        });
    }
    public function down(): void { Schema::dropIfExists('product_units'); }
};
