# Libya Passport Reader 🇱🇾

**Privacy-first Laravel API for reading and validating passport MRZ data from text, images, and PDF scans.**

Libya Passport Reader is an independent open-source developer project focused on turning machine-readable passport data into structured, validated JSON. It targets the two-line **TD3 MRZ** defined by ICAO Doc 9303 and provides a Libya-focused workflow without hard-coding unverified passport-specific assumptions.

> This is not an official Libyan government service and is not presented as being approved, endorsed, or affiliated with Libyan passport authorities. A mathematically valid MRZ is not proof that a passport is genuine.

## v0.2 OCR scanner

- TD3 MRZ parsing and ICAO check-digit validation
- image upload from camera/gallery (`JPEG`, `PNG`, `WebP`)
- first-page PDF scanning
- local Tesseract OCR
- automatic MRZ candidate detection and check-digit scoring
- structured JSON output
- private temporary processing with immediate cleanup
- raw OCR text is never returned by the API
- no passport-image persistence
- no passport-data persistence
- OpenAPI 3.1 and automated Laravel tests

The repository and tests use synthetic MRZ data only. **Never commit real passport images, MRZ lines, passport numbers, or personal data.**

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

The response contains parsed passport fields, ICAO check-digit validation, OCR-engine metadata, and privacy metadata. It does **not** return raw OCR text and it does not claim passport authenticity verification.

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
brew install tesseract poppler
```

Ubuntu/Debian:

```bash
sudo apt-get update
sudo apt-get install -y tesseract-ocr poppler-utils
```

`poppler-utils` provides `pdftoppm`, which is used only when the uploaded input is a PDF.

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
6. deletes every temporary source/raster file in a `finally` block before returning the response.

The application does not create passport database records. Full MRZ strings, raw OCR output, and full passport numbers must not be written to logs.

See [SECURITY.md](SECURITY.md).

## Roadmap

### v0.3 — visual-zone fields
- Arabic/English visual-zone extraction where reliably detectable
- place of birth, issue date, and issuing place where supported
- compare visible-zone OCR with MRZ data
- mismatch/confidence reporting

### Later
- Flutter live camera scanner
- optional on-device OCR
- ePassport/NFC research only where technically and legally appropriate

## License

MIT © 2026 Aya Aljaidi
