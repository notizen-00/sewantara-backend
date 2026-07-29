<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->string('provisioning_status', 30)
                ->default('awaiting_payment')
                ->index();
            $table->timestampTz('provisioned_at')->nullable();
            $table->text('provisioning_error')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropColumn([
                'provisioning_status',
                'provisioned_at',
                'provisioning_error',
            ]);
        });
    }
};
