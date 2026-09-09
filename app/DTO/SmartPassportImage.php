<?php

namespace App\DTO;

final readonly class SmartPassportImage
{
    public function __construct(
        public string $mrzImagePath,
        public string $visualZoneImagePath,
        public array $temporaryPaths = [],
        public string $strategy = 'full_image_fallback',
        public array $preprocessing = [],
        public array $mrzCandidatePaths = [],
    ) {}
}
