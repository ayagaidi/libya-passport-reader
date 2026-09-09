<?php

namespace App\Services\Passport\Vision;

use App\DTO\VisionPassportImage;

interface VisionPassportImageProcessorInterface
{
    public function prepare(string $imagePath): VisionPassportImage;
}
