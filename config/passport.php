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

    'smart_scanner' => [
        'enabled' => (bool) env('PASSPORT_SMART_SCANNER_ENABLED', true),
        'imagemagick_binary' => env('PASSPORT_IMAGEMAGICK_BINARY', 'magick'),
        'timeout' => (int) env('PASSPORT_SMART_SCANNER_TIMEOUT', 15),
        'max_dimension' => (int) env('PASSPORT_SMART_SCANNER_MAX_DIMENSION', 2600),
        'deskew_threshold' => env('PASSPORT_SMART_SCANNER_DESKEW', '40%'),
        'contrast_stretch' => env('PASSPORT_SMART_SCANNER_CONTRAST', '1%x1%'),
        'mrz_start_ratio' => (float) env('PASSPORT_SMART_SCANNER_MRZ_START', 0.62),
        'mrz_candidate_start_ratios' => array_map(
            'floatval',
            explode(',', (string) env('PASSPORT_SMART_SCANNER_MRZ_CANDIDATES', '0.54,0.60,0.66')),
        ),
        'max_mrz_candidates' => (int) env('PASSPORT_SMART_SCANNER_MAX_MRZ_CANDIDATES', 4),
        'visual_end_ratio' => (float) env('PASSPORT_SMART_SCANNER_VISUAL_END', 0.78),
    ],

    'visual_zone' => [
        'enabled' => (bool) env('PASSPORT_VISUAL_ZONE_ENABLED', true),
        'language' => env('PASSPORT_VISUAL_OCR_LANGUAGE', 'eng+ara'),
        'fallback_language' => env('PASSPORT_VISUAL_OCR_FALLBACK_LANGUAGE', 'eng'),
        'psm' => (int) env('PASSPORT_VISUAL_OCR_PSM', 6),
        'timeout' => (int) env('PASSPORT_VISUAL_OCR_TIMEOUT', 20),
    ],

    'pdf' => [
        'pdftoppm_binary' => env('PASSPORT_PDFTOPPM_BINARY', 'pdftoppm'),
        'dpi' => (int) env('PASSPORT_PDF_DPI', 300),
    ],
];
