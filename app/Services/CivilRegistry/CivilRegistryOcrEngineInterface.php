<?php

namespace App\Services\CivilRegistry;

use App\DTO\OcrResult;

interface CivilRegistryOcrEngineInterface
{
    public function read(string $imagePath): OcrResult;
}
