<?php

namespace App\DTO;

final readonly class OcrResult
{
    public function __construct(
        public string $engine,
        public string $text,
        public ?float $confidence = null,
        public array $metadata = [],
    ) {}
}
