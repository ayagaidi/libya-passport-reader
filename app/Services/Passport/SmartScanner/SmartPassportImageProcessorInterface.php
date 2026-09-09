<?php

namespace App\Services\Passport\SmartScanner;

use App\DTO\SmartPassportImage;

interface SmartPassportImageProcessorInterface
{
    public function prepare(string $imagePath): SmartPassportImage;
}
