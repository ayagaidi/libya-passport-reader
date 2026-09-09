# Libya Document Reader 🇱🇾

**English** | [العربية](README_AR.md)

**Privacy-first Laravel API for reading Libyan passports and supported Civil Registry Authority documents from images and PDF scans.**

Libya Document Reader is an independent open-source developer project. It combines local OCR, computer vision, ICAO TD3 MRZ validation, structured Arabic/English extraction, and conservative document-verification signals without claiming official document authenticity.

> This is not an official Libyan government service and is not approved, endorsed, or affiliated with Libyan passport or Civil Registry authorities. OCR, MRZ validation, QR presence, template consistency, or detected seals are not proof that a document is genuine.

## v0.4.1 Civil Registry Verification Signals

v0.4.1 extends the Civil Registry reader with advisory verification signals:

- QR detection using OpenCV
- local QR decoding when image quality allows it
- structural validation of recognized `CheckNumber:<UUID>` payloads
- QR position consistency for the currently supported sample layouts
- blue-ink seal/stamp candidate detection
- normalized seal candidate positions without returning image crops
- sample-calibrated template-anchor coverage
- field-format consistency checks for national numbers and dates
- aggregate `signal_score`
- statuses: `signals_consistent`, `partial_signals`, `review_recommended`, or `insufficient_evidence`
- no issuer URL is called automatically
- no raw QR payload is returned
- `authenticity_verified` always remains `false` without a future trusted issuer integration

The currently supported Civil Registry layouts are:

- Residence Certificate — **شهادة الإقامة**
- Family Status Certificate — **شهادة بالوضع العائلي**

Template checks are calibrated from supported sample layouts and are deliberately not presented as an official template certification.

## v0.4 Civil Registry Reader

Civil Registry scans use dedicated Arabic/English Tesseract OCR with multiple page-segmentation candidates (`PSM 4`, `3`, and `11`). The strongest OCR candidate is selected conservatively and useful lines are merged for extraction.

Civil Registry OCR and verification use the **complete prepared page** so portrait headers, QR blocks, and table headings are not lost to passport-oriented crops. Passport vision output is still available as diagnostic metadata but is not applied to Civil Registry OCR.

Family Status Certificate extraction can return detected family-member rows with fields such as:

- national number
- name
- relationship
- date of birth

Residence Certificate extraction can return detected fields such as:

- national number
- family registry number
- family sheet number
- person name
- father name
- mother name
- date of birth
- profession
- address
- registered-since date

Fields are returned only when the local OCR/extractor detects them; missing fields are not invented.

## v0.3.3 Vision Passport Scanner

The passport scanner includes:

- OpenCV contour-based document detection
- four-corner perspective correction
- landscape normalization for passport pages
- blur, glare, and overexposure quality scoring
- ImageMagick normalization and MRZ-region candidates
- adaptive local-threshold MRZ preprocessing
- TD3 candidate scoring using ICAO structure/check digits
- Arabic + English visual-zone OCR
- conservative MRZ/visual comparison

## Scanner pipelines

Passport:

`upload/PDF → document detection → perspective correction → quality gate → smart preprocessing → adaptive MRZ OCR → ICAO validation → Arabic/English visual OCR → comparison`

Civil Registry:

`upload/PDF → full-page preparation → multi-layout Arabic/English OCR → document classification → structured extraction → QR/seal detection → template/field consistency → verification signals`

## API

### Scan a passport

`POST /api/v1/passport/scan`

```bash
curl -X POST http://localhost:8000/api/v1/passport/scan \
  -H 'Accept: application/json' \
  -F 'passport=@passport.jpg'
```

### Scan a Civil Registry document

`POST /api/v1/civil-registry/scan`

```bash
curl -X POST http://localhost:8000/api/v1/civil-registry/scan \
  -H 'Accept: application/json' \
  -F 'document=@civil-registry.pdf'
```

A successful Civil Registry response can include:

```json
{
  "data": {
    "document": {
      "type": "residence_certificate",
      "fields": {}
    },
    "verification": {
      "status": "partial_signals",
      "signal_score": 0.71,
      "authenticity_verified": false,
      "issuer_verification": {
        "status": "not_configured",
        "database_checked": false,
        "digital_signature_verified": false
      },
      "qr": {
        "detected": true,
        "decoded": true,
        "payload_format": "civil_registry_check_number",
        "structure_valid": true,
        "position_consistent": true,
        "issuer_lookup_performed": false
      },
      "seals": {
        "detected": true,
        "candidate_count": 1,
        "expected_location_match": true
      },
      "template": {
        "status": "consistent",
        "official_template_verified": false
      }
    }
  }
}
```

The example is illustrative and contains no real identity data.

### Parse MRZ text directly

`POST /api/v1/passport/mrz/parse`

```json
{
  "line1": "P<LBYALGAIDI<<AYA<<<<<<<<<<<<<<<<<<<<<<<<<<<",
  "line2": "1234567897LBY9501016F3001019<<<<<<<<<<<<<<02"
}
```

This MRZ is fictional/synthetic test data.

### Other endpoints

- `POST /api/v1/passport/mrz/validate`
- `GET /api/v1/meta`
- `GET /health`
- `GET /openapi.yaml`
- Swagger UI: `/docs`

## Privacy

The API is designed to process identity documents transiently:

- uploaded files are copied into random private temporary storage
- PDF processing rasterizes only the first page
- OCR runs locally
- OpenCV and ImageMagick run locally
- raw OCR text is not returned
- raw QR payloads are not returned
- document images are not persisted by the application
- extracted document data is not persisted by the application
- all tracked temporary files are deleted in `finally` cleanup before a successful response returns

Never log raw OCR/TSV output, full MRZ strings, QR payloads, passport images, Civil Registry images, or extracted identity fields.

The repository and automated tests use synthetic data only. **Never commit real identity documents or personal data.**

## Local setup

### 1. Laravel

```bash
composer install
cp .env.example .env
php artisan key:generate
```

### 2. OCR and image dependencies

macOS with Homebrew:

```bash
brew install tesseract tesseract-lang poppler imagemagick python
```

Ubuntu/Debian:

```bash
sudo apt-get update
sudo apt-get install -y tesseract-ocr tesseract-ocr-ara poppler-utils imagemagick python3 python3-venv
```

### 3. OpenCV environment

```bash
python3 -m venv .venv-vision
.venv-vision/bin/python -m pip install --upgrade pip
.venv-vision/bin/python -m pip install -r requirements-vision.txt
```

Configure the worker:

```env
PASSPORT_VISION_ENABLED=true
PASSPORT_VISION_PYTHON_BINARY=/absolute/path/to/project/.venv-vision/bin/python
PASSPORT_VISION_REJECT_LOW_QUALITY=true

CIVIL_REGISTRY_OCR_LANGUAGE=ara+eng
CIVIL_REGISTRY_OCR_PSMS=4,3,11
CIVIL_REGISTRY_OCR_TIMEOUT=30
CIVIL_REGISTRY_VERIFICATION_ENABLED=true
CIVIL_REGISTRY_VERIFICATION_TIMEOUT=15
CIVIL_REGISTRY_VERIFICATION_MAX_DIMENSION=2200
```

### 4. Run

```bash
php artisan serve
```

Open:

- API: `http://localhost:8000`
- Swagger: `http://localhost:8000/docs`

## Verification model

Civil Registry verification is intentionally split into levels:

1. **Extraction** — OCR and structured fields.
2. **Structural signals** — national-number/date formats and recognized QR payload format.
3. **Visual signals** — QR position and seal/stamp candidates.
4. **Template signals** — expected text anchors for supported sample layouts.
5. **Issuer verification** — **not implemented** until a trusted official verification source or cryptographic signature mechanism is available.

Only level 5 could materially raise the system toward authoritative authenticity verification. The API therefore does not label a document as genuine or forged.

## Security notes

- QR payloads are decoded locally and are never automatically opened as URLs.
- unrecognized QR contents are represented only by a SHA-256 fingerprint, not returned raw
- external commands are invoked using argument arrays rather than shell interpolation
- seal detection uses image features only and cannot prove who applied a stamp
- template profiles are sample-calibrated, not official government schemas

See [SECURITY.md](SECURITY.md).

## Roadmap

### v0.4.x

- improve Arabic table-row reconstruction
- add confidence-based field suppression
- add more Civil Registry layouts only from safe synthetic/redacted calibration samples
- improve QR recovery for low-resolution and skewed scans
- add non-blue/monochrome seal research without weakening false-positive controls
- optional trusted issuer verification if an official, documented integration becomes available

### Later

- Flutter live camera scanner with framing guidance
- optional on-device OCR/vision
- ePassport/NFC research only where technically and legally appropriate

## License

MIT © 2026 Aya Aljaidi
