# Security & Privacy

Libya Document Reader handles identity-document data, so privacy is a product requirement.

## Current guarantees

- Passport MRZ text endpoints do not persist submitted data.
- Passport and Civil Registry scanners accept JPEG, PNG, WebP, or PDF input and process it only in private temporary storage.
- Temporary filenames are random and use restrictive permissions where the platform permits.
- PDF scanning rasterizes only the first page for OCR.
- Tesseract OCR, ImageMagick preprocessing, OpenCV vision, QR detection, and seal detection run locally by default.
- Perspective-corrected frames, PDF rasters, normalized images, and OCR crops are private temporary artifacts.
- Temporary processing files are deleted through scanner `finally` cleanup lifecycles.
- Raw OCR text and Tesseract TSV output are not returned by scanner APIs.
- Raw Civil Registry QR payloads are not returned. Recognized check-number payloads expose only the parsed check number; unrecognized decoded payloads expose only a SHA-256 fingerprint.
- QR contents are never automatically opened as URLs and no issuer website is called by the QR worker.
- Civil Registry visual verification returns geometry/confidence metadata, not seal or QR image crops.
- Extracted identity fields are returned only in the request response and are not persisted by the application.
- Passport MRZ/visual comparison and Civil Registry verification signals are consistency checks, not authenticity decisions.
- `authenticity_verified` remains `false` without a trusted issuer or cryptographic verification mechanism.
- Application code must not log raw OCR, TSV output, full MRZ lines, QR payloads, extracted identity fields, uploaded document images, corrected images, or derived crops.

## Civil Registry verification boundaries

Civil Registry verification currently includes:

- QR presence and local decoding
- structural recognition of `CheckNumber:<UUID>` payloads
- expected QR-position consistency for supported sample layouts
- blue-ink seal/stamp visual candidates
- sample-calibrated template text anchors
- national-number and date-format consistency

These signals can identify missing or inconsistent features, but they cannot prove that a seal is genuine, that a QR was issued by the Civil Registry Authority, or that a document is authentic. Template profiles are calibrated from supported sample layouts and are not official government schemas.

Do not add automatic network calls based on arbitrary QR contents. A future issuer-verification integration must use a separately configured, trusted endpoint with strict allow-listing, timeouts, TLS validation, response-size limits, and no redirect to untrusted hosts.

## Deployment

Run OCR, vision, QR, seal detection, and preprocessing locally. The default OCR engine is Tesseract, smart image preprocessing uses ImageMagick, passport vision uses `tools/passport_vision.py`, and Civil Registry visual signals use `tools/civil_registry_verify.py`.

Deployments should use HTTPS, restrictive filesystem permissions, request-size limits, rate limits, process timeouts, isolated runtime users, and short infrastructure log retention. Reverse proxies and observability products must not capture request or response bodies for identity-document endpoints.

Install ImageMagick and Python/OpenCV from trusted package sources and keep them patched. Use a dedicated Python virtual environment for `requirements-vision.txt`; do not install unrelated runtime packages into that environment.

External processes are invoked with argument arrays rather than shell interpolation. Production wrappers must not replace this with shell-interpolated commands or log private temporary paths.

If Arabic OCR is enabled, ensure the required local Tesseract language pack is installed.

Image-quality thresholds and document-verification scores are operational heuristics, not authenticity controls. Calibrate them only with synthetic or appropriately redacted/private evaluation material; never publish real identity documents in issues, repositories, or CI artifacts.

## Reporting

Please report security issues privately to the repository owner rather than opening a public issue containing identity-document data.

Never include real passport numbers, MRZ lines, Civil Registry national numbers, QR payloads, document images, OCR output, corrected frames, or other personal data in public bug reports.
