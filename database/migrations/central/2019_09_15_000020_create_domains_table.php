<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDomainsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('domains', function (Blueprint $table) {
            $table->increments('id');
            $table->string('domain', 255)->unique();
            $table->string('tenant_id');
            $table->boolean('is_primary')->default(false);
            $table->string('verification_status', 30)->default('pending');
            $table->string('verification_token')->nullable();
            $table->timestampTz('verified_at')->nullable();
            $table->string('ssl_status', 30)->default('pending');
            $table->timestampsTz();
            $table->foreign('tenant_id')->references('id')->on('tenants')->onUpdate('cascade')->onDelete('cascade');
            $table->index(['tenant_id', 'is_primary']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('domains');
    }
}
