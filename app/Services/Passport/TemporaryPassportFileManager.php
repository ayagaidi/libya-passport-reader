<?php

namespace App\Services\Passport;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use RuntimeException;

final class TemporaryPassportFileManager
{
    public function copyFromUpload(UploadedFile $file): string
    {
        $directory = storage_path((string) config('passport.temp_directory', 'app/private/passport-tmp'));

        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException('Unable to create the private passport-processing directory.');
        }

        @chmod($directory, 0700);

        $source = $file->getRealPath();

        if ($source === false) {
            throw new RuntimeException('Unable to access the uploaded passport document.');
        }

        $path = $directory.DIRECTORY_SEPARATOR.Str::uuid()->toString().'.'.$this->extensionFor($file);

        if (! copy($source, $path)) {
            throw new RuntimeException('Unable to create a temporary passport-processing file.');
        }

        @chmod($path, 0600);

        return $path;
    }

    public function delete(array $paths): void
    {
        foreach (array_unique(array_filter($paths)) as $path) {
            if (is_file($path) && ! unlink($path)) {
                throw new RuntimeException('Unable to delete a temporary passport-processing file.');
            }
        }
    }

    private function extensionFor(UploadedFile $file): string
    {
        return match ($file->getMimeType() ?: $file->getClientMimeType()) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf',
            default => throw new RuntimeException('Unsupported passport document type.'),
        };
    }
}
