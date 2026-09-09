<?php

namespace App\DTO;

final readonly class VisionPassportImage
{
    public function __construct(
        public string $imagePath,
        public array $temporaryPaths = [],
        public string $strategy = 'vision_fallback',
        public bool $documentDetected = false,
        public bool $perspectiveCorrected = false,
        public array $quality = [],
        public array $diagnostics = [],
    ) {}
}
