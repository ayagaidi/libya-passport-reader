<?php

return [
    'max_upload_kb' => (int) env('PASSPORT_MAX_UPLOAD_KB', 10240),
    'temp_directory' => env('PASSPORT_TEMP_DIRECTORY', 'app/private/passport-tmp'),

    'ocr' => [
        'engine' => env('PASSPORT_OCR_ENGINE', 'tesseract'),
        'tesseract_binary' => env('PASSPORT_TESSERACT_BINARY', 'tesseract'),
        'language' => env('PASSPORT_OCR_LANGUAGE', 'eng'),
        'psm' => (int) env('PASSPORT_OCR_PSM', 6),
        'timeout' => (int) env('PASSPORT_OCR_TIMEOUT', 20),
    ],

    'pdf' => [
        'pdftoppm_binary' => env('PASSPORT_PDFTOPPM_BINARY', 'pdftoppm'),
        'dpi' => (int) env('PASSPORT_PDF_DPI', 300),
    ],
];
