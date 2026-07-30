<?php

use App\Modules\RentalEngine\Application\RentalEngine;
use Database\Seeders\BusinessTemplateSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite.database', ':memory:');
    DB::purge('sqlite');
    DB::setDefaultConnection('sqlite');

    Schema::create('rental_configurations', function (Blueprint $table): void {
        $table->id();
        $table->string('tenant_id');
        $table->string('rental_model');
        $table->string('booking_strategy');
        $table->string('allocation_strategy');
        $table->integer('slot_duration_minutes')->nullable();
        $table->boolean('allow_walk_in')->default(true);
        $table->boolean('allow_online_booking')->default(true);
        $table->boolean('realtime_availability')->default(true);
        $table->timestamps();
    });
});

test('queue rental derives its period pricing and allocation from tenant configuration', function () {
    DB::table('rental_configurations')->insert([
        'id' => 1,
        'tenant_id' => 'tenant-a',
        'rental_model' => 'per_hour',
        'booking_strategy' => 'queue',
        'allocation_strategy' => 'auto_assign',
        'slot_duration_minutes' => 60,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $engine = app(RentalEngine::class);
    $booking = $engine->prepareBooking([
        'start_at' => '2026-08-01 10:00:00',
        'end_at' => null,
    ]);

    expect($engine->pricingType())->toBe('hourly')
        ->and($engine->usesAutoAssignment())->toBeTrue()
        ->and($booking['end_at']->format('H:i'))->toBe('11:00')
        ->and($engine->billableDuration(
            $booking['start_at'],
            $booking['end_at'],
            1,
        ))->toBe(1);
});

test('session rental rejects periods outside its configured slot', function () {
    DB::table('rental_configurations')->insert([
        'id' => 2,
        'tenant_id' => 'tenant-a',
        'rental_model' => 'session',
        'booking_strategy' => 'session',
        'allocation_strategy' => 'manual',
        'slot_duration_minutes' => 60,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(fn () => app(RentalEngine::class)->prepareBooking([
        'start_at' => '2026-08-01 10:00:00',
        'end_at' => '2026-08-01 11:30:00',
    ]))->toThrow(ValidationException::class);
});

test('business templates provide configuration presets without hardcoded categories', function () {
    $templates = collect(BusinessTemplateSeeder::templates())->keyBy('code');

    expect($templates)->toHaveKeys([
        'car_rental',
        'playstation_rental',
        'camera_rental',
        'sports_field',
        'custom',
    ])->and($templates['playstation_rental']['configuration'])
        ->toMatchArray([
            'rental_model' => 'per_hour',
            'booking_strategy' => 'queue',
            'allocation_strategy' => 'auto_assign',
            'slot_duration_minutes' => 60,
        ]);
});
