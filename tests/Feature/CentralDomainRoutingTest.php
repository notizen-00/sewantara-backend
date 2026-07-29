<?php

beforeEach(function () {
    config()->set('session.driver', 'array');
});

test('central domains expose backend service information', function (string $domain) {
    $this->get("http://{$domain}")
        ->assertOk()
        ->assertJson([
            'success' => true,
            'data' => [
                'service' => 'Sewantara API',
                'status' => 'running',
            ],
        ]);
})->with([
    'localhost' => 'localhost',
    'loopback address' => '127.0.0.1',
]);

test('the central landing route does not initialize tenancy', function () {
    $this->get('http://localhost')->assertOk();

    expect(tenancy()->initialized)->toBeFalse();
});

test('local tenant URLs use localhost as their base domain', function () {
    expect(config('tenancy.tenant_base_domain'))->toBe('localhost');
});
