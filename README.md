# Libya Passport Reader 🇱🇾

**Privacy-first Laravel API for reading and validating passport MRZ data from text, images, and PDF scans.**

Libya Passport Reader is an independent open-source developer project focused on turning machine-readable passport data into structured, validated JSON. It targets the two-line **TD3 MRZ** defined by ICAO Doc 9303 and provides a Libya-focused workflow without hard-coding unverified passport-specific assumptions.

> This is not an official Libyan government service and is not presented as being approved, endorsed, or affiliated with Libyan passport authorities. A mathematically valid MRZ or a visual-zone match is not proof that a passport is genuine.

## v0.3.2 Adaptive Passport Scanner

v0.3.2 strengthens the v0.3.1 smart preprocessing pipeline by making MRZ region selection adaptive instead of relying on one crop.

- configurable ImageMagick preprocessing
- automatic EXIF orientation correction
- grayscale normalization
- deskew
- contrast stretch and sharpening
- bounded image resizing
- multiple configurable TD3 MRZ crop candidates
- normalized-image and original-image fallbacks
- local OCR on every viable MRZ candidate
- ICAO/check-digit detection scoring for every OCR result
- OCR confidence used only as a small quality tie-breaker
- automatic selection of the strongest MRZ candidate
- configurable visual-zone crop
- Arabic/English visual OCR with real Tesseract TSV confidence
- all normalized/cropped images remain private temporary files and are deleted before the request returns

This substantially reduces dependence on a single fixed MRZ vertical position, which is useful for mobile photos where the passport may be framed differently.

The scanner still does **not** claim passport authenticity verification. True detected document-corner geometry and perspective correction remain separate computer-vision enhancements.

## Existing scanner capabilities

- TD3 MRZ parsing and ICAO check-digit validation
- image upload from camera/gallery (`JPEG`, `PNG`, `WebP`)
- first-page PDF scanning
- local Tesseract OCR
- automatic MRZ candidate detection and check-digit scoring
- Arabic + English visual-zone OCR
- labeled-field extraction for names, passport number, nationality, dates, sex, place of birth, issue date, and issuing place
- comparison of MRZ-backed visual fields against parsed MRZ data
- `match`, `mismatch`, `not_detected`, and `not_comparable` reporting
- visual-only fields kept separate when the MRZ has no equivalent
- structured JSON output
- private temporary processing with immediate cleanup
- raw OCR text is never returned by the API
- no passport-image persistence
- no passport-data persistence
- OpenAPI 3.1 and automated Laravel tests

Arabic names are **not automatically treated as equal to Latin MRZ transliterations**. The API reports them as `not_comparable` unless a safe comparison can be made. This avoids pretending that transliteration is deterministic.

The repository and tests use synthetic data only. **Never commit real passport images, MRZ lines, passport numbers, or personal data.**

## API

### Scan a passport image or PDF

`POST /api/v1/passport/scan`

Send `multipart/form-data` with a file field named `passport`.

```bash
curl -X POST http://localhost:8000/api/v1/passport/scan \
  -H 'Accept: application/json' \
  -F 'passport=@passport.jpg'
```

A mobile or Flutter camera can send the captured frame to the same endpoint.

The response contains parsed MRZ fields, ICAO validation, adaptive smart-scanner metadata, OCR-engine metadata, extracted visual-zone fields, MRZ/visual comparisons, and privacy metadata. It does **not** return raw OCR text and it does not claim passport authenticity verification.

Example smart-scanner metadata:

```json
{
  "scan": {
    "mrz_detected": true,
    "smart_scanner": {
      "strategy": "imagemagick_adaptive_regions",
      "region_detection_applied": true,
      "adaptive_mrz": {
        "enabled": true,
        "candidate_count": 5,
        "attempts": 5,
        "selected_candidate": 1,
        "detection_score": 35
      }
    }
  }
}
```

No temporary file path is returned in the response.

If smart preprocessing cannot be used, the API reports `full_image_fallback` and continues with the existing privacy-first scanner behavior.

### Parse MRZ text directly

`POST /api/v1/passport/mrz/parse`

```json
{
  "line1": "P<LBYALGAIDI<<AYA<<<<<<<<<<<<<<<<<<<<<<<<<<<",
  "line2": "1234567897LBY9501016F3001019<<<<<<<<<<<<<<02"
}
```

The example above is fictional/synthetic test data.

### Validate MRZ

`POST /api/v1/passport/mrz/validate`

### Metadata

`GET /api/v1/meta`

### Health

`GET /health`

### OpenAPI

`GET /openapi.yaml`

## Local setup

### 1. Application

```bash
composer install
cp .env.example .env
php artisan key:generate
```

### 2. OCR and smart-scanner dependencies

macOS with Homebrew:

```bash
brew install tesseract tesseract-lang poppler imagemagick
```

Ubuntu/Debian:

```bash
sudo apt-get update
sudo apt-get install -y tesseract-ocr tesseract-ocr-ara poppler-utils imagemagick
```

`poppler-utils` provides `pdftoppm`, which is used only when the uploaded input is a PDF.

The visual-zone scanner defaults to `eng+ara`. If Arabic language data is unavailable, it can fall back to English using `PASSPORT_VISUAL_OCR_FALLBACK_LANGUAGE=eng`.

The smart scanner defaults to the ImageMagick 7 executable `magick`. On systems using another compatible executable, set `PASSPORT_IMAGEMAGICK_BINARY` accordingly. The feature can be disabled with `PASSPORT_SMART_SCANNER_ENABLED=false`.

Adaptive MRZ start ratios can be tuned with:

```env
PASSPORT_SMART_SCANNER_MRZ_CANDIDATES=0.54,0.60,0.66
PASSPORT_SMART_SCANNER_MAX_MRZ_CANDIDATES=4
```

The configured primary ratio is also included in the candidate set. The scanner then adds normalized/full-image fallbacks internally.

### 3. Run

```bash
php artisan serve
```

Then visit `http://localhost:8000/health`.

## Privacy lifecycle

For `/passport/scan` the server:

1. accepts the upload after MIME and size validation;
2. copies it to a randomly named private temporary path with restrictive permissions;
3. rasterizes only page 1 when the input is a PDF;
4. optionally normalizes the image and creates multiple temporary MRZ crops plus a visual-zone crop;
5. runs local MRZ OCR across the private candidate images;
6. scores detected TD3 candidates using ICAO structure/check digits and selects the strongest result;
7. parses and validates the selected MRZ;
8. optionally runs Arabic/English visual-zone OCR on the visual crop;
9. extracts labeled visual fields and compares MRZ-backed fields;
10. deletes the upload, PDF raster, normalized image, and every region crop in a `finally` block before returning the response.

The application does not create passport database records. Full MRZ strings, raw OCR output, and temporary file paths must not be written to logs.

See [SECURITY.md](SECURITY.md).

## Comparison model

Visual fields that have MRZ equivalents can be compared:

- surname
- given names
- passport number
- nationality
- date of birth
- sex
- expiry date

Visual-zone fields without a TD3 MRZ equivalent are returned separately and are not presented as MRZ-verified:

- place of birth
- issue date
- issuing place / authority

A successful comparison means the OCR-visible value and MRZ value are consistent after conservative normalization. It is **not** an authenticity decision.

## Roadmap

### v0.3.x
- detected document-corner geometry and perspective correction
- stronger glare/shadow and blur handling
- layout-profile calibration using private synthetic/redacted evaluation data
- confidence thresholds and optional low-confidence field suppression

### Later
- Flutter live camera scanner
- optional on-device OCR
- ePassport/NFC research only where technically and legally appropriate

## License

MIT © 2026 Aya Aljaidi
