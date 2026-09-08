<?php

namespace App\Services\Passport\Ocr;

use App\DTO\OcrResult;

interface OcrEngineInterface
{
    public function read(string $imagePath): OcrResult;
}
