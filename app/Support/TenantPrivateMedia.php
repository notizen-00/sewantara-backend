<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TenantPrivateMedia
{
    public const DISK = 'local';

    private const ALLOWED_DIRECTORIES = [
        'branding/',
        'branches/',
        'categories/',
        'products/',
        'articles/',
        'articles/',
    ];

    public function store(UploadedFile $file, string $directory): string
    {
        $path = $file->store(trim($directory, '/'), self::DISK);

        if ($path === false) {
            throw new RuntimeException('File gambar gagal disimpan.');
        }

        return $path;
    }

    public function delete(?string $path): void
    {
        if ($path !== null && $path !== '') {
            Storage::disk(self::DISK)->delete($path);
        }
    }

    public function url(?string $path): ?string
    {
        if ($path === null || $path === '' || ! app()->bound('currentTenant')) {
            return null;
        }

        return route('tenant.media.show', [
            'tenant' => app('currentTenant')->getTenantKey(),
            'path' => $path,
        ]);
    }

    public function response(string $path): StreamedResponse
    {
        $path = str_replace('\\', '/', trim($path, '/'));

        abort_if(
            $path === ''
                || str_contains($path, '..')
                || ! preg_match('/^[A-Za-z0-9._\/-]+$/', $path)
                || ! $this->isAllowedPath($path)
                || ! Storage::disk(self::DISK)->exists($path),
            404,
        );

        return Storage::disk(self::DISK)->response(
            $path,
            basename($path),
            [
                'Cache-Control' => 'private, max-age=3600',
                'Content-Disposition' => 'inline',
            ],
        );
    }

    private function isAllowedPath(string $path): bool
    {
        foreach (self::ALLOWED_DIRECTORIES as $directory) {
            if (str_starts_with($path, $directory)) {
                return true;
            }
        }

        return false;
    }
}
