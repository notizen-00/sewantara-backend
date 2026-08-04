<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rental_configurations', function (Blueprint $table): void {
            $table->string('engine_code', 20)->nullable()->after('tenant_id');
        });

        // A tenant's existing single configuration row is classified as the
        // Booking engine when it uses anything other than the classic
        // date-range rental strategy (queue or session), otherwise Rental.
        // This correctly classifies session/queue-based setups such as
        // PS-per-jam (per_hour + queue) as Booking, not just session+session.
        DB::statement(<<<'SQL'
            UPDATE rental_configurations
            SET engine_code = CASE
                WHEN booking_strategy = 'date_range' THEN 'rental'
                ELSE 'booking'
            END
        SQL);

        DB::statement('ALTER TABLE rental_configurations ALTER COLUMN engine_code SET NOT NULL');
        DB::statement('ALTER TABLE rental_configurations DROP CONSTRAINT rental_configurations_tenant_id_unique');

        Schema::table('rental_configurations', function (Blueprint $table): void {
            $table->unique(['tenant_id', 'engine_code']);
        });
    }

    public function down(): void
    {
        Schema::table('rental_configurations', function (Blueprint $table): void {
            $table->dropUnique(['tenant_id', 'engine_code']);
        });

        DB::statement('ALTER TABLE rental_configurations ADD CONSTRAINT rental_configurations_tenant_id_unique UNIQUE (tenant_id)');

        Schema::table('rental_configurations', function (Blueprint $table): void {
            $table->dropColumn('engine_code');
        });
    }
};
