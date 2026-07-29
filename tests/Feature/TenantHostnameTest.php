<?php

use App\Support\TenantHostname;

test('tenant hostname combines the subdomain and configured base domain', function () {
    config()->set('tenancy.tenant_base_domain', 'localhost');
    config()->set('app.url', 'http://localhost');

    $hostname = TenantHostname::fromSubdomain('KendoKenceng');

    expect($hostname)->toBe('kendokenceng.localhost')
        ->and(TenantHostname::url($hostname))
        ->toBe('http://kendokenceng.localhost');
});
