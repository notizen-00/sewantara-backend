<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->uuid('public_id')->nullable()->unique();
            $table->boolean('public_web_enabled')->default(false)->index();
            $table->string('locale', 10)->default('id-ID');
        });

        DB::table('tenants')->whereNull('public_id')->orderBy('id')
            ->get(['id'])
            ->each(fn (object $tenant) => DB::table('tenants')
                ->where('id', $tenant->id)
                ->update(['public_id' => (string) Str::uuid()]));

        Schema::table('tenants', function (Blueprint $table): void {
            $table->uuid('public_id')->nullable(false)->change();
        });

        Schema::table('domains', function (Blueprint $table): void {
            $table->string('type', 30)->default('subdomain')->index();
            $table->string('status', 30)->default('pending')->index();
        });

        DB::table('domains')->orderBy('id')->get([
            'id',
            'domain',
            'verification_status',
        ])->each(function (object $domain): void {
            $hostname = strtolower((string) $domain->domain);
            $baseDomain = strtolower(trim((string) config(
                'tenancy.tenant_base_domain',
            ), '.'));
            $subdomain = $baseDomain !== ''
                && str_ends_with($hostname, '.'.$baseDomain);
            $verified = $domain->verification_status === 'verified';

            DB::table('domains')->where('id', $domain->id)->update([
                'type' => $subdomain ? 'subdomain' : 'custom_domain',
                'status' => $verified ? 'active' : 'pending',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('domains', function (Blueprint $table): void {
            $table->dropIndex(['type']);
            $table->dropIndex(['status']);
            $table->dropColumn(['type', 'status']);
        });

        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropIndex(['public_web_enabled']);
            $table->dropUnique(['public_id']);
            $table->dropColumn(['public_id', 'public_web_enabled', 'locale']);
        });
    }
};
