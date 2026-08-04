<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_engines', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id')->index();
            $table->foreignId('engine_id')->constrained()->restrictOnDelete();
            $table->boolean('is_enabled')->default(true);
            $table->timestampTz('enabled_at')->nullable();
            $table->timestampTz('disabled_at')->nullable();
            $table->decimal('price_snapshot', 18, 2)->default(0);
            $table->timestampsTz();
            $table->unique(['tenant_id', 'engine_id']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
        });

        DB::statement('ALTER TABLE tenant_engines ADD CONSTRAINT tenant_engines_price_snapshot_nonnegative CHECK (price_snapshot >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_engines');
    }
};
