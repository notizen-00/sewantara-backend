<?php

test('database cache always uses the central database connection', function () {
    expect(config('cache.stores.database.connection'))
        ->not->toBeNull()
        ->not->toBe('tenant');
});
