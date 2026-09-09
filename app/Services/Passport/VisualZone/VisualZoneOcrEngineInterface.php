<?php

namespace App\Services\Passport\VisualZone;

use App\DTO\OcrResult;

interface VisualZoneOcrEngineInterface
{
    public function read(string $imagePath): OcrResult;
}
