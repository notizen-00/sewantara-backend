<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->string('engine_code', 20)->nullable()->after('category_id');
            $table->string('product_type', 20)->nullable()->after('engine_code');
        });

        // Every product created before this feature existed was implicitly a
        // Rental-engine product (Booking/Membership/Sales engines did not
        // exist yet). product_type is intentionally left null — there is no
        // reliable signal to infer a specific type from existing columns;
        // tenants can set it via product update.
        DB::table('products')->update(['engine_code' => 'rental']);

        DB::statement('ALTER TABLE products ALTER COLUMN engine_code SET NOT NULL');

        Schema::table('products', function (Blueprint $table): void {
            $table->index(['tenant_id', 'engine_code']);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropIndex(['tenant_id', 'engine_code']);
            $table->dropColumn(['engine_code', 'product_type']);
        });
    }
};
