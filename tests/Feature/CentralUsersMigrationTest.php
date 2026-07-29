<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('central migration creates the platform users table', function () {
    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite.database', ':memory:');
    DB::purge('sqlite');
    DB::setDefaultConnection('sqlite');

    $migration = require database_path(
        'migrations/central/0001_01_01_000000_create_users_table.php',
    );

    $migration->up();

    expect(Schema::hasColumns('users', [
        'id',
        'name',
        'email',
        'phone',
        'password',
        'avatar_path',
        'is_active',
        'email_verified_at',
        'last_login_at',
        'remember_token',
        'created_at',
        'updated_at',
        'deleted_at',
    ]))->toBeTrue()
        ->and(Schema::hasColumn('users', 'tenant_id'))->toBeFalse();

    $emailIndex = collect(Schema::getIndexes('users'))
        ->first(fn (array $index): bool => $index['columns'] === ['email']);

    expect($emailIndex)->not->toBeNull()
        ->and($emailIndex['unique'])->toBeTrue();

    $migration->down();
});
