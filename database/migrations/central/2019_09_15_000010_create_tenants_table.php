<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTenantsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name', 150);
            $table->string('slug', 150)->unique();
            $table->string('business_type', 100)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('phone', 30)->nullable();
            $table->text('address')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('timezone', 50)->default('Asia/Jakarta');
            $table->char('currency', 3)->default('IDR');
            $table->string('status', 30)->default('active')->index();
            $table->timestampTz('activated_at')->nullable();
            $table->timestampTz('suspended_at')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
            $table->jsonb('data')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
}
