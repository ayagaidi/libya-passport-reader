<?php

namespace App\Providers;

use App\Services\Passport\Ocr\OcrEngineInterface;
use App\Services\Passport\Ocr\TesseractOcrEngine;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(OcrEngineInterface::class, TesseractOcrEngine::class);
    }

    public function boot(): void
    {
        //
    }
}
