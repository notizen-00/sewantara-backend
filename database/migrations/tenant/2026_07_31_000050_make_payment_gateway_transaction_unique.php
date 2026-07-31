<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table): void {
            $table->dropIndex(['gateway', 'transaction_id']);
            $table->unique(['gateway', 'transaction_id']);
        });
    }

    public function down(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table): void {
            $table->dropUnique(['gateway', 'transaction_id']);
            $table->index(['gateway', 'transaction_id']);
        });
    }
};
