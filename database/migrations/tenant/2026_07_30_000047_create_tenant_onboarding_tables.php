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
        Schema::create('tenant_business_profiles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('tenant_id')->unique();
            $table->string('template_code', 100);
            $table->unsignedInteger('template_version')->default(1);
            $table->string('business_name', 150);
            $table->string('timezone', 50)->default('Asia/Jakarta');
            $table->char('currency', 3)->default('IDR');
            $table->jsonb('operating_hours')->nullable();
            $table->timestampsTz();
        });

        Schema::create('rental_configurations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('tenant_id')->unique();
            $table->string('rental_model', 30);
            $table->string('booking_strategy', 30);
            $table->string('allocation_strategy', 30);
            $table->unsignedInteger('slot_duration_minutes')->nullable();
            $table->boolean('enable_waiting_list')->default(false);
            $table->boolean('allow_walk_in')->default(true);
            $table->boolean('allow_online_booking')->default(true);
            $table->boolean('allow_extend_booking')->default(false);
            $table->boolean('realtime_availability')->default(true);
            $table->boolean('auto_reminder')->default(true);
            $table->boolean('auto_cancel_unpaid')->default(false);
            $table->unsignedInteger('auto_cancel_minutes')->nullable();
            $table->unsignedInteger('engine_version')->default(1);
            $table->timestampsTz();
        });

        Schema::create('tenant_onboarding', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('tenant_id')->unique();
            $table->string('status', 30)->default('in_progress');
            $table->string('current_step', 50)->default('business_setup');
            $table->jsonb('completed_steps')->default('[]');
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();
        });

        Schema::create('tenant_payment_methods', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('tenant_id')->index();
            $table->string('method', 30);
            $table->boolean('is_enabled')->default(false);
            $table->text('configuration')->nullable();
            $table->timestampsTz();
            $table->unique(['tenant_id', 'method']);
        });

        $tenantId = (string) tenant('id');

        if ($tenantId !== '') {
            $now = now();

            DB::table('tenant_business_profiles')->insert([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'template_code' => 'custom',
                'template_version' => 1,
                'business_name' => (string) (tenant('name') ?: 'Rental Business'),
                'timezone' => (string) (tenant('timezone') ?: 'Asia/Jakarta'),
                'currency' => (string) (tenant('currency') ?: 'IDR'),
                'operating_hours' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('rental_configurations')->insert([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'rental_model' => 'per_day',
                'booking_strategy' => 'date_range',
                'allocation_strategy' => 'manual',
                'slot_duration_minutes' => null,
                'enable_waiting_list' => false,
                'allow_walk_in' => true,
                'allow_online_booking' => true,
                'allow_extend_booking' => false,
                'realtime_availability' => true,
                'auto_reminder' => true,
                'auto_cancel_unpaid' => false,
                'auto_cancel_minutes' => null,
                'engine_version' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('tenant_onboarding')->insert([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'status' => tenant('status') === 'active' ? 'completed' : 'in_progress',
                'current_step' => tenant('status') === 'active' ? 'go_live' : 'business_setup',
                'completed_steps' => tenant('status') === 'active'
                    ? json_encode([
                        'business_setup',
                        'business_template',
                        'rental_configuration',
                        'inventory_setup',
                        'pricing',
                        'booking_configuration',
                        'payment_configuration',
                        'go_live',
                    ], JSON_THROW_ON_ERROR)
                    : '[]',
                'completed_at' => tenant('status') === 'active' ? $now : null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_payment_methods');
        Schema::dropIfExists('tenant_onboarding');
        Schema::dropIfExists('rental_configurations');
        Schema::dropIfExists('tenant_business_profiles');
    }
};
