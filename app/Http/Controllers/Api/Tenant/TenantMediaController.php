<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Support\TenantPrivateMedia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TenantMediaController extends Controller
{
    public function __invoke(
        string $path,
        TenantPrivateMedia $media,
    ): StreamedResponse {
        return $media->response($path);
    }
}
