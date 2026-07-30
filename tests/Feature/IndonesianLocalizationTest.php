<?php

use Illuminate\Support\Facades\Validator;

it('uses Indonesian as the default application language', function () {
    expect(config('app.locale'))->toBe('id')
        ->and(config('app.fallback_locale'))->toBe('id');
});

it('returns Indonesian validation messages with readable attribute names', function () {
    $validator = Validator::make([], [
        'business_name' => ['required'],
        'owner.password' => ['required'],
    ]);

    expect($validator->errors()->first('business_name'))
        ->toBe('nama usaha wajib diisi.')
        ->and($validator->errors()->first('owner.password'))
        ->toBe('kata sandi pemilik wajib diisi.');
});
