# Security & Privacy

Libya Passport Reader handles identity-document data, so privacy is a product requirement.

## Current guarantees

- MRZ text endpoints do not persist submitted passport data.
- The scanner accepts JPEG, PNG, WebP, or PDF input and processes it only in private temporary storage.
- Temporary filenames are random and use restrictive permissions where the platform permits.
- PDF scanning rasterizes only the first page for OCR.
- v0.3.3 OpenCV vision processing runs locally and does not send the passport image to a third-party service.
- Perspective-corrected frames are private temporary artifacts and enter the same deterministic cleanup lifecycle as PDF rasters, normalized images, and OCR crops.
- The vision worker returns only safe processing metadata to Laravel; it does not return OCR text, passport fields, corner coordinates, or temporary paths through the public API.
- Severe blur/glare rejection occurs before OCR when the quality gate is enabled.
- ImageMagick smart preprocessing and OpenCV vision processing are executed with argument arrays rather than shell command interpolation.
- If OpenCV or ImageMagick is unavailable, the application falls back to a less enhanced private scan path rather than persisting derived artifacts.
- Temporary upload, raster, corrected, normalized, and region-crop files are deleted through the scanner `finally` cleanup lifecycle.
- MRZ OCR and Arabic/English visual-zone OCR run locally by default.
- Raw OCR text and Tesseract TSV output are not returned by the scanner API.
- OCR bounding boxes are used only in-process for confidence-aware extraction metadata.
- Extracted visual-zone fields are returned only as part of the request response and are not persisted by the application.
- Visual/MRZ comparison is a consistency check, not a passport-authenticity decision.
- The application does not create passport database records.
- Application code must not log full MRZ lines, raw OCR text, TSV output, extracted identity fields, full passport numbers, uploaded images, corrected images, or derived crops.

## Deployment

Run OCR, vision, and preprocessing locally. The default OCR engine is Tesseract, the optional smart image processor uses ImageMagick, and v0.3.3 vision uses the local Python/OpenCV worker in `tools/passport_vision.py`.

Deployments should use HTTPS, restrictive filesystem permissions, request-size limits, rate limits, process timeouts, isolated runtime users, and short infrastructure log retention. Reverse proxies and observability products must be configured not to capture request or response bodies for passport endpoints.

Install ImageMagick and Python/OpenCV from trusted package sources and keep them patched. Use a dedicated Python virtual environment for `requirements-vision.txt`; do not install unrelated runtime packages into that environment.

The OpenCV worker accepts input/output paths supplied by the application and emits JSON metadata on stdout. Production wrappers must not replace this with shell-interpolated commands or log the process arguments when those arguments contain private temporary file paths.

If Arabic visual OCR is enabled, ensure the required local Tesseract language pack is installed. The scanner may fall back to English OCR when configured to do so.

Quality thresholds are operational heuristics, not authenticity controls. Calibrate blur/glare thresholds using synthetic or appropriately redacted evaluation material and do not publish real passport samples in issues or CI artifacts.

## Reporting

Please report security issues privately to the repository owner rather than opening a public issue containing passport data.

Never include real passport numbers, MRZ lines, passport images, OCR output, corrected frames, or other personal data in bug reports.
