# Security & Privacy

Libya Passport Reader handles identity-document data, so privacy is a product requirement.

## Current guarantees

- MRZ text endpoints do not persist submitted passport data.
- The v0.2 scanner accepts JPEG, PNG, WebP, or PDF input and processes it only in private temporary storage.
- Temporary filenames are random and are created with restrictive permissions where the platform permits.
- PDF scanning rasterizes only the first page for OCR.
- Temporary upload and raster files are deleted in a `finally` block before a successful API response is returned.
- Raw OCR text is not returned by the scanner API.
- The application does not create passport database records.
- Application code must not log full MRZ lines, raw OCR text, full passport numbers, or uploaded identity-document images.

## Deployment

Run OCR locally when possible. The default engine is Tesseract and no passport image is sent to a third-party OCR provider by the project.

Deployments should use encrypted transport (HTTPS), restrictive filesystem permissions, request-size limits, rate limits, and short infrastructure log retention. Reverse proxies and observability products must be configured not to capture request bodies for passport endpoints.

## Reporting

Please report security issues privately to the repository owner rather than opening a public issue containing passport data.

Never include real passport numbers, MRZ lines, passport images, or other personal data in bug reports.
