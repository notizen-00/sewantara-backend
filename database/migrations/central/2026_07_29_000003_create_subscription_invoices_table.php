<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('subscription_invoices', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('tenant_id')->index();
            $table->foreignId('plan_subscription_id')
                ->constrained(config('laravel-subscriptions.tables.subscriptions'))
                ->restrictOnDelete();
            $table->string('invoice_number', 100);
            $table->date('issue_date');
            $table->date('due_date')->nullable();
            $table->decimal('amount', 18, 2);
            $table->char('currency', 3)->default('IDR');
            $table->string('status', 30)->index();
            $table->string('pdf_path')->nullable();
            $table->timestampsTz();
            $table->unique(['tenant_id', 'invoice_number']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
        });
    }
    public function down(): void { Schema::dropIfExists('subscription_invoices'); }
};
