<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_business_profiles', function (Blueprint $table): void {
            $table->string('primary_engine_code', 20)->default('rental')->after('tenant_id');
        });

        // At this point every tenant still has exactly one rental_configurations
        // row, so this is a direct copy, not a re-application of the backfill
        // heuristic from the previous migration.
        DB::statement(<<<'SQL'
            UPDATE tenant_business_profiles
            SET primary_engine_code = rental_configurations.engine_code
            FROM rental_configurations
            WHERE rental_configurations.tenant_id = tenant_business_profiles.tenant_id
        SQL);
    }

    public function down(): void
    {
        Schema::table('tenant_business_profiles', function (Blueprint $table): void {
            $table->dropColumn('primary_engine_code');
        });
    }
};
