<?php

namespace App\Exceptions;

use RuntimeException;

final class LowQualityPassportImageException extends RuntimeException
{
    public function __construct(private readonly array $quality)
    {
        parent::__construct('The passport image quality is too low for reliable OCR.');
    }

    public function quality(): array
    {
        return $this->quality;
    }
}
