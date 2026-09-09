# Security & Privacy

Libya Passport Reader handles identity-document data, so privacy is a product requirement.

## Current guarantees

- MRZ text endpoints do not persist submitted passport data.
- The scanner accepts JPEG, PNG, WebP, or PDF input and processes it only in private temporary storage.
- Temporary filenames are random and are created with restrictive permissions where the platform permits.
- PDF scanning rasterizes only the first page for OCR.
- v0.3.1 smart preprocessing creates only private temporary normalized/cropped images and includes them in the same deterministic cleanup lifecycle.
- Smart preprocessing runs through argument-array process execution rather than shell command interpolation.
- If ImageMagick is unavailable or preprocessing fails, the scanner falls back to the original private image rather than persisting a derived artifact.
- Temporary upload, raster, normalized, and region-crop files are deleted before a successful API response is returned.
- MRZ OCR and Arabic/English visual-zone OCR run locally by default.
- Raw OCR text and Tesseract TSV output are not returned by the scanner API.
- OCR bounding boxes are used only in-process for confidence-aware extraction metadata.
- Extracted visual-zone fields are returned only as part of the request response and are not persisted by the application.
- Visual/MRZ comparison is a consistency check, not a passport-authenticity decision.
- The application does not create passport database records.
- Application code must not log full MRZ lines, raw OCR text, TSV output, extracted identity fields, full passport numbers, or uploaded/derived identity-document images.

## Deployment

Run OCR and preprocessing locally when possible. The default OCR engine is Tesseract and the optional smart image processor uses ImageMagick. No passport image is sent to a third-party OCR provider by the project.

Deployments should use encrypted transport (HTTPS), restrictive filesystem permissions, request-size limits, rate limits, process timeouts, and short infrastructure log retention. Reverse proxies and observability products must be configured not to capture request bodies or response bodies for passport endpoints.

Install ImageMagick from a trusted operating-system package source and keep it patched. Disable unneeded ImageMagick coders/policies in production, especially formats not accepted by this API. The application accepts only its validated JPEG, PNG, WebP, or first-page PDF workflow.

If visual-zone OCR is enabled with Arabic language data, ensure the required Tesseract language pack is installed locally. The scanner may fall back to English OCR when configured to do so.

## Reporting

Please report security issues privately to the repository owner rather than opening a public issue containing passport data.

Never include real passport numbers, MRZ lines, passport images, OCR output, or other personal data in bug reports.
