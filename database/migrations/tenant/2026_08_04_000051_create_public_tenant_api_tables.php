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
        Schema::table('branches', function (Blueprint $table): void {
            $table->boolean('is_public')->default(false)->index();
            $table->boolean('is_primary')->default(false)->index();
        });

        Schema::table('categories', function (Blueprint $table): void {
            $table->uuid('public_id')->nullable()->unique();
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->uuid('public_id')->nullable()->unique();
            $table->boolean('is_public')->default(false)->index();
            $table->timestampTz('published_at')->nullable()->index();
            $table->jsonb('specifications')->nullable();
            $table->string('seo_title', 200)->nullable();
            $table->text('seo_description')->nullable();
            $table->text('booking_rules')->nullable();
            $table->text('cancellation_policy')->nullable();
        });

        Schema::table('product_images', function (Blueprint $table): void {
            $table->uuid('public_id')->nullable()->unique();
        });

        Schema::table('bookings', function (Blueprint $table): void {
            $table->uuid('public_id')->nullable()->unique();
            $table->string('tracking_token_hash', 64)->nullable()->index();
            $table->timestampTz('expires_at')->nullable();
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->uuid('public_id')->nullable()->unique();
        });

        $now = now();

        DB::table('branches')->where('is_active', true)->update([
            'is_public' => true,
        ]);

        DB::table('branches')
            ->where('is_active', true)
            ->orderBy('id')
            ->get(['tenant_id', 'id'])
            ->groupBy('tenant_id')
            ->each(function ($branches): void {
                $branchIds = $branches->pluck('id');
                $pricedBranchId = DB::table('product_prices')
                    ->whereIn('branch_id', $branchIds)
                    ->where('is_active', true)
                    ->selectRaw('branch_id, COUNT(*) AS price_count')
                    ->groupBy('branch_id')
                    ->orderByDesc('price_count')
                    ->orderBy('branch_id')
                    ->value('branch_id');

                DB::table('branches')
                    ->where('id', $pricedBranchId ?? $branches->first()->id)
                    ->update(['is_primary' => true]);
            });

        foreach (['categories', 'products', 'product_images', 'bookings', 'payments'] as $table) {
            DB::table($table)->whereNull('public_id')->orderBy('id')
                ->get(['id'])
                ->each(fn (object $record) => DB::table($table)
                    ->where('id', $record->id)
                    ->update(['public_id' => (string) Str::uuid()]));
        }

        DB::table('products')->where('is_active', true)->update([
            'is_public' => true,
            'published_at' => $now,
        ]);

        foreach (['categories', 'products', 'product_images', 'bookings', 'payments'] as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->uuid('public_id')->nullable(false)->change();
            });
        }

        DB::statement(
            'CREATE UNIQUE INDEX branches_one_public_primary '
            .'ON branches (tenant_id) WHERE is_primary = true AND deleted_at IS NULL',
        );

        Schema::create('public_articles', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id')->index();
            $table->uuid('public_id')->unique();
            $table->string('slug', 200);
            $table->string('title', 200);
            $table->text('excerpt')->nullable();
            $table->longText('body_html');
            $table->string('cover_image_path')->nullable();
            $table->string('seo_title', 200)->nullable();
            $table->text('seo_description')->nullable();
            $table->boolean('is_published')->default(false)->index();
            $table->timestampTz('published_at')->nullable()->index();
            $table->timestampsTz();
            $table->softDeletesTz();
            $table->unique(['tenant_id', 'slug']);
        });

        Schema::create('public_quotes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('tenant_id')->index();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('variant_id', 100)->nullable();
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');
            $table->unsignedInteger('quantity');
            $table->jsonb('request_snapshot');
            $table->jsonb('pricing_snapshot');
            $table->string('request_hash', 64);
            $table->timestampTz('expires_at')->index();
            $table->timestampTz('used_at')->nullable();
            $table->timestampsTz();
            $table->index(['tenant_id', 'product_id', 'expires_at']);
        });

        Schema::create('stock_holds', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('tenant_id')->index();
            $table->uuid('quote_id')->nullable();
            $table->foreignId('booking_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('variant_id', 100)->nullable();
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');
            $table->unsignedInteger('quantity');
            $table->timestampTz('expires_at')->index();
            $table->string('status', 30)->default('active')->index();
            $table->timestampsTz();
            $table->foreign('quote_id')->references('id')->on('public_quotes')->nullOnDelete();
            $table->index(['tenant_id', 'product_id', 'starts_at', 'ends_at']);
        });

        Schema::create('idempotency_records', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id')->index();
            $table->string('idempotency_key', 64);
            $table->string('endpoint', 150);
            $table->string('request_hash', 64);
            $table->string('status', 30)->default('in_progress');
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->jsonb('response_body')->nullable();
            $table->string('resource_type', 100)->nullable();
            $table->string('resource_id', 100)->nullable();
            $table->timestampTz('expires_at')->index();
            $table->timestampsTz();
            $table->unique(
                ['tenant_id', 'endpoint', 'idempotency_key'],
                'idempotency_tenant_endpoint_key_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_records');
        Schema::dropIfExists('stock_holds');
        Schema::dropIfExists('public_quotes');
        Schema::dropIfExists('public_articles');

        DB::statement('DROP INDEX IF EXISTS branches_one_public_primary');

        Schema::table('product_images', function (Blueprint $table): void {
            $table->dropUnique(['public_id']);
            $table->dropColumn('public_id');
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropUnique(['public_id']);
            $table->dropColumn('public_id');
        });

        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropUnique(['public_id']);
            $table->dropIndex(['tracking_token_hash']);
            $table->dropColumn(['public_id', 'tracking_token_hash', 'expires_at']);
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->dropUnique(['public_id']);
            $table->dropIndex(['is_public']);
            $table->dropIndex(['published_at']);
            $table->dropColumn([
                'public_id',
                'is_public',
                'published_at',
                'specifications',
                'seo_title',
                'seo_description',
                'booking_rules',
                'cancellation_policy',
            ]);
        });

        Schema::table('categories', function (Blueprint $table): void {
            $table->dropUnique(['public_id']);
            $table->dropColumn('public_id');
        });

        Schema::table('branches', function (Blueprint $table): void {
            $table->dropIndex(['is_public']);
            $table->dropIndex(['is_primary']);
            $table->dropColumn(['is_public', 'is_primary']);
        });
    }
};
