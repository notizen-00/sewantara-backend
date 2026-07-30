<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('tenant_id')
                ->nullable()
                ->after('id')
                ->index();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->nullOnDelete();
        });

        DB::table('tenants')
            ->whereNotNull('email')
            ->orderBy('id')
            ->get(['id', 'email'])
            ->each(function (object $tenant): void {
                DB::table('users')
                    ->whereNull('tenant_id')
                    ->whereRaw('LOWER(email) = ?', [
                        mb_strtolower(trim((string) $tenant->email)),
                    ])
                    ->update(['tenant_id' => $tenant->id]);
            });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['tenant_id']);
            $table->dropColumn('tenant_id');
        });
    }
};
