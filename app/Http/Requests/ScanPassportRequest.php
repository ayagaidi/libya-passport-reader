<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ScanPassportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'passport' => [
                'required',
                'file',
                'max:'.config('passport.max_upload_kb', 10240),
                'mimetypes:image/jpeg,image/png,image/webp,application/pdf',
            ],
        ];
    }
}
