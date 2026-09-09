# Libya Passport Reader 🇱🇾

**Privacy-first Laravel API for reading and validating passport MRZ data from text, images, and PDF scans.**

Libya Passport Reader is an independent open-source developer project focused on turning machine-readable passport data into structured, validated JSON. It targets the two-line **TD3 MRZ** defined by ICAO Doc 9303 and provides a Libya-focused workflow without hard-coding unverified passport-specific assumptions.

> This is not an official Libyan government service and is not presented as being approved, endorsed, or affiliated with Libyan passport authorities. A mathematically valid MRZ or a visual-zone match is not proof that a passport is genuine.

## v0.3 visual-zone OCR

v0.3 extends the privacy-first v0.2 scanner with a second OCR pass for visible passport fields.

- TD3 MRZ parsing and ICAO check-digit validation
- image upload from camera/gallery (`JPEG`, `PNG`, `WebP`)
- first-page PDF scanning
- local Tesseract OCR
- automatic MRZ candidate detection and check-digit scoring
- Arabic + English visual-zone OCR
- labeled-field extraction for names, passport number, nationality, dates, sex, place of birth, issue date, and issuing place
- comparison of MRZ-backed visual fields against parsed MRZ data
- `match`, `mismatch`, `not_detected`, and `not_comparable` reporting
- confidence metadata for extracted labeled fields
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

The response contains parsed MRZ fields, ICAO check-digit validation, OCR-engine metadata, extracted visual-zone fields, MRZ/visual comparisons, and privacy metadata. It does **not** return raw OCR text and it does not claim passport authenticity verification.

Example response shape:

```json
{
  "data": {
    "passport": {
      "document_format": "TD3",
      "data": {},
      "validation": {}
    },
    "scan": {
      "mrz_detected": true,
      "ocr_engine": "tesseract"
    },
    "visual_zone": {
      "status": "processed",
      "ocr_engine": "tesseract-visual:eng+ara",
      "fields": {},
      "visual_only_fields": {},
      "comparison": {
        "status": "consistent",
        "matches": 0,
        "mismatches": 0,
        "fields": {}
      }
    },
    "privacy": {
      "stores_passport_images": false,
      "stores_passport_data": false,
      "returns_raw_ocr_text": false,
      "temporary_files_deleted": true
    }
  },
  "meta": {
    "standard": "ICAO Doc 9303 TD3",
    "document_authenticity_verified": false
  }
}
```

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

### 2. OCR dependencies

macOS with Homebrew:

```bash
brew install tesseract tesseract-lang poppler
```

Ubuntu/Debian:

```bash
sudo apt-get update
sudo apt-get install -y tesseract-ocr tesseract-ocr-ara poppler-utils
```

`poppler-utils` provides `pdftoppm`, which is used only when the uploaded input is a PDF.

The visual-zone scanner defaults to `eng+ara`. If Arabic language data is unavailable, it can fall back to English using `PASSPORT_VISUAL_OCR_FALLBACK_LANGUAGE=eng`.

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
4. runs local OCR and detects TD3 MRZ candidates;
5. parses and validates the MRZ;
6. optionally runs Arabic/English visual-zone OCR on the same temporary raster;
7. extracts labeled visual fields and compares MRZ-backed fields;
8. deletes every temporary source/raster file in a `finally` block before returning the response.

The application does not create passport database records. Full MRZ strings and raw OCR output must not be written to logs.

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
- visual-region detection/cropping before visible-field OCR
- deskew, contrast, and mobile-photo preprocessing
- improved per-field confidence from OCR word boxes
- configurable passport-layout profiles without hard-coded official assumptions

### Later
- Flutter live camera scanner
- optional on-device OCR
- ePassport/NFC research only where technically and legally appropriate

## License

MIT © 2026 Aya Aljaidi
