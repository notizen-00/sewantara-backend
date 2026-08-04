<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('engines', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->decimal('monthly_price', 18, 2)->default(0);
            $table->boolean('is_core')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });

        DB::statement("ALTER TABLE engines ADD CONSTRAINT engines_code_known_values CHECK (code IN ('rental', 'booking', 'membership', 'sales'))");
        DB::statement('ALTER TABLE engines ADD CONSTRAINT engines_monthly_price_nonnegative CHECK (monthly_price >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('engines');
    }
};
