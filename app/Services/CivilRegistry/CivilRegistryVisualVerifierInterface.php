<?php

namespace App\Services\CivilRegistry;

interface CivilRegistryVisualVerifierInterface
{
    public function verify(string $imagePath, string $documentType): array;
}
