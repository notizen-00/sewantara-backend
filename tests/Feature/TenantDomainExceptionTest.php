<?php

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Stancl\Tenancy\Exceptions\TenantCouldNotBeIdentifiedOnDomainException;

test('an unknown tenant domain is always rendered as a safe json response', function () {
    $request = Request::create('http://unknown.example.test');
    $exception = new TenantCouldNotBeIdentifiedOnDomainException($request->getHost());

    $response = app(ExceptionHandler::class)->render($request, $exception);

    expect($response->getStatusCode())->toBe(404)
        ->and($response->headers->get('content-type'))->toContain('application/json')
        ->and($response->getData(true))->toBe([
            'success' => false,
            'error' => [
                'code' => 'DOMAIN_NOT_FOUND',
                'message' => 'Domain ini tidak terdaftar.',
                'details' => null,
            ],
        ]);
});
