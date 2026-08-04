<?php

namespace App\Support;

use Illuminate\Filesystem\FilesystemAdapter;
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
        'demo/branding/',
        'demo/branches/',
        'demo/categories/',
        'demo/products/',
        'demo/articles/',
    ];

    public function store(
        UploadedFile $file,
        string $directory,
    ): string {
        $path = $file->store(
            trim($directory, '/'),
            self::DISK,
        );

        if ($path === false) {
            throw new RuntimeException(
                'File gambar gagal disimpan.',
            );
        }

        return $path;
    }

    public function delete(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        $this->disk()->delete($path);
    }

    public function url(?string $path): ?string
    {
        if (
            $path === null
            || $path === ''
            || ! app()->bound('currentTenant')
        ) {
            return null;
        }

        $relativeUrl = route(
            'tenant.media.show',
            [
                'tenant' => app('currentTenant')
                    ->getTenantKey(),
                'path' => $path,
            ],
            false,
        );

        return rtrim(
            (string) config('app.url'),
            '/',
        ) . $relativeUrl;
    }

    public function response(string $path): StreamedResponse
    {
        $path = str_replace(
            '\\',
            '/',
            trim($path, '/'),
        );

        $disk = $this->disk();

        abort_if(
            $path === ''
                || str_contains($path, '..')
                || ! preg_match(
                    '/^[A-Za-z0-9._\/-]+$/',
                    $path,
                )
                || ! $this->isAllowedPath($path)
                || ! $disk->exists($path),
            404,
        );

        return $disk->response(
            $path,
            basename($path),
            [
                'Cache-Control' => 'public, max-age=86400',
                'Content-Disposition' => 'inline',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }

    private function disk(): FilesystemAdapter
    {
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk(self::DISK);

        return $disk;
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
