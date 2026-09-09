<?php

namespace App\Providers;

use App\Services\Passport\Ocr\OcrEngineInterface;
use App\Services\Passport\Ocr\TesseractOcrEngine;
use App\Services\Passport\SmartScanner\ImageMagickSmartPassportImageProcessor;
use App\Services\Passport\SmartScanner\SmartPassportImageProcessorInterface;
use App\Services\Passport\VisualZone\TesseractVisualZoneOcrEngine;
use App\Services\Passport\VisualZone\VisualZoneOcrEngineInterface;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(OcrEngineInterface::class, TesseractOcrEngine::class);
        $this->app->bind(VisualZoneOcrEngineInterface::class, TesseractVisualZoneOcrEngine::class);
        $this->app->bind(SmartPassportImageProcessorInterface::class, ImageMagickSmartPassportImageProcessor::class);
    }

    public function boot(): void
    {
        //
    }
}
