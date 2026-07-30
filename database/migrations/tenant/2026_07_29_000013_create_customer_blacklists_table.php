<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_blacklists', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id')->index();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->text('reason');
            $table->foreignId('blacklisted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('blacklisted_at');
            $table->timestampTz('removed_at')->nullable();
            $table->foreignId('removed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('removal_notes')->nullable();
            $table->timestampsTz();
            $table->index(['tenant_id', 'customer_id', 'removed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_blacklists');
    }
};
