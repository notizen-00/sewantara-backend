<?php

namespace App\Modules\PublicApi\Read\Support;

final readonly class PublicMediaAsset
{
    public function __construct(
        public string $disk,
        public string $path,
        public string $mimeType,
        public int $size,
        public int $lastModified,
    ) {}
}
