<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ScanCivilRegistryDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'document' => [
                'required',
                'file',
                'max:'.config('passport.max_upload_kb', 30720),
                'mimetypes:image/jpeg,image/png,image/webp,application/pdf',
            ],
        ];
    }
}
