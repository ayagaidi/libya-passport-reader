<?php

return [
    'max_upload_kb' => (int) env('PASSPORT_MAX_UPLOAD_KB', 30720),
    'temp_directory' => env('PASSPORT_TEMP_DIRECTORY', 'app/private/passport-tmp'),

    'ocr' => [
        'engine' => env('PASSPORT_OCR_ENGINE', 'tesseract'),
        'tesseract_binary' => env('PASSPORT_TESSERACT_BINARY', 'tesseract'),
        'language' => env('PASSPORT_OCR_LANGUAGE', 'eng'),
        'psm' => (int) env('PASSPORT_OCR_PSM', 6),
        'timeout' => (int) env('PASSPORT_OCR_TIMEOUT', 20),
    ],

    'vision' => [
        'enabled' => (bool) env('PASSPORT_VISION_ENABLED', true),
        'python_binary' => env('PASSPORT_VISION_PYTHON_BINARY', 'python3'),
        'timeout' => (int) env('PASSPORT_VISION_TIMEOUT', 12),
        'max_detection_dimension' => (int) env('PASSPORT_VISION_MAX_DIMENSION', 1400),
        'min_document_area_ratio' => (float) env('PASSPORT_VISION_MIN_DOCUMENT_AREA', 0.20),
        'blur_warning_threshold' => (float) env('PASSPORT_VISION_BLUR_WARNING', 75),
        'blur_reject_threshold' => (float) env('PASSPORT_VISION_BLUR_REJECT', 35),
        'glare_warning_ratio' => (float) env('PASSPORT_VISION_GLARE_WARNING', 0.18),
        'glare_reject_ratio' => (float) env('PASSPORT_VISION_GLARE_REJECT', 0.35),
        'overexposure_warning_ratio' => (float) env('PASSPORT_VISION_OVEREXPOSURE_WARNING', 0.80),
        'overexposure_reject_ratio' => (float) env('PASSPORT_VISION_OVEREXPOSURE_REJECT', 0.95),
        'reject_low_quality' => (bool) env('PASSPORT_VISION_REJECT_LOW_QUALITY', true),
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
        'mrz_adaptive_threshold_enabled' => (bool) env('PASSPORT_MRZ_ADAPTIVE_THRESHOLD_ENABLED', true),
        'mrz_adaptive_target_width' => (int) env('PASSPORT_MRZ_ADAPTIVE_TARGET_WIDTH', 3200),
        'mrz_adaptive_block_size' => (int) env('PASSPORT_MRZ_ADAPTIVE_BLOCK_SIZE', 41),
        'mrz_adaptive_constant' => (float) env('PASSPORT_MRZ_ADAPTIVE_CONSTANT', 15),
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
