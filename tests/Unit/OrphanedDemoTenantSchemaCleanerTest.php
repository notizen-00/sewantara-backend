<?php

use Database\Seeders\Support\OrphanedDemoTenantSchemaCleaner;

test('demo schema cleaner only accepts tenant schemas with valid UUID names', function () {
    $cleaner = new OrphanedDemoTenantSchemaCleaner;

    expect($cleaner->isSafeTenantSchemaName(
        'tenant3e44d56f-129c-4818-beae-1c8f8c2c7d21',
        'tenant',
        '',
    ))->toBeTrue()
        ->and($cleaner->isSafeTenantSchemaName('public', 'tenant', ''))->toBeFalse()
        ->and($cleaner->isSafeTenantSchemaName('tenant', 'tenant', ''))->toBeFalse()
        ->and($cleaner->isSafeTenantSchemaName('tenant-demo', 'tenant', ''))->toBeFalse()
        ->and($cleaner->isSafeTenantSchemaName(
            'tenant3e44d56f-129c-4818-0eae-1c8f8c2c7d21',
            'tenant',
            '',
        ))->toBeFalse();
});

test('demo schema cleaner respects configured prefix and suffix', function () {
    $cleaner = new OrphanedDemoTenantSchemaCleaner;

    expect($cleaner->isSafeTenantSchemaName(
        'workspace_3e44d56f-129c-4818-beae-1c8f8c2c7d21_demo',
        'workspace_',
        '_demo',
    ))->toBeTrue()
        ->and($cleaner->isSafeTenantSchemaName(
            'tenant3e44d56f-129c-4818-beae-1c8f8c2c7d21',
            'workspace_',
            '_demo',
        ))->toBeFalse();
});
