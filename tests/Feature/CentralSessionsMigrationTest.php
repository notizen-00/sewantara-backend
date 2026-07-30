<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('central migration creates the database session table', function () {
    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite.database', ':memory:');
    DB::purge('sqlite');
    DB::setDefaultConnection('sqlite');

    $migration = require database_path(
        'migrations/central/2026_07_30_000008_create_sessions_table.php',
    );

    $migration->up();

    expect(Schema::hasColumns('sessions', [
        'id',
        'user_id',
        'ip_address',
        'user_agent',
        'payload',
        'last_activity',
    ]))->toBeTrue();

    $migration->down();

    expect(Schema::hasTable('sessions'))->toBeFalse();
});
