<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ParseMrzRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'line1' => $this->normalize($this->input('line1')),
            'line2' => $this->normalize($this->input('line2')),
        ]);
    }

    public function rules(): array
    {
        return [
            'line1' => ['required', 'string', 'size:44', 'regex:/^[A-Z0-9<]{44}$/'],
            'line2' => ['required', 'string', 'size:44', 'regex:/^[A-Z0-9<]{44}$/'],
        ];
    }

    private function normalize(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        return strtoupper(preg_replace('/\s+/', '', trim($value)) ?? '');
    }
}
