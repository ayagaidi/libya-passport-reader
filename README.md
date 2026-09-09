# Libya Passport Reader 🇱🇾

**Privacy-first Laravel API for reading and validating passport MRZ data from text, images, and PDF scans.**

Libya Passport Reader is an independent open-source developer project focused on ICAO Doc 9303 TD3 passports. It combines local OCR, conservative MRZ validation, bilingual visible-field extraction, and a privacy-first computer-vision pipeline without claiming document authenticity.

> This is not an official Libyan government service and is not approved, endorsed, or affiliated with Libyan passport authorities. A valid MRZ, corrected document image, or visual-zone match is not proof that a passport is genuine.

## v0.3.3 Vision Passport Scanner

v0.3.3 adds a real computer-vision stage before the existing adaptive OCR pipeline.

- OpenCV contour-based document detection
- best-quadrilateral selection from detected page boundaries
- rotated-rectangle fallback for strong rectangular contours
- four-corner ordering and perspective transform
- automatic landscape normalization after perspective correction
- blur quality scoring using Laplacian variance
- glare/overexposure scoring using clipped low-saturation highlights
- `accepted`, `warning`, and `rejected` image-quality states
- configurable hard rejection for severely low-quality frames
- privacy-safe vision metadata without corner coordinates or temporary paths
- safe fallback to v0.3.2 when OpenCV is disabled, unavailable, or no document contour is confidently detected
- synthetic OpenCV tests in a dedicated GitHub Actions job

The vision stage runs before ImageMagick preprocessing. A successfully rectified document is then passed into the existing adaptive MRZ scanner, which still tries multiple MRZ crops and ranks candidates primarily using ICAO structure/check-digit evidence.

## Scanner pipeline

`upload/PDF → document detection → perspective correction → quality gate → smart preprocessing → adaptive MRZ OCR → MRZ validation → Arabic/English visual OCR → MRZ/visual comparison`

Current capabilities include:

- JPEG, PNG, WebP, and first-page PDF scanning
- local Tesseract OCR
- TD3 MRZ parsing and ICAO check-digit validation
- multiple configurable MRZ region candidates
- ICAO-based MRZ candidate ranking with OCR confidence only as a small tie-breaker
- Arabic + English visual-zone OCR
- real Tesseract TSV confidence for visible-field OCR
- labeled-field extraction for names, passport number, nationality, dates, sex, place of birth, issue date, and issuing place
- conservative comparison of visible fields against MRZ data
- private temporary processing and deterministic cleanup
- no raw OCR response
- no passport-image persistence
- no passport-data persistence
- OpenAPI 3.1, Laravel tests, and dedicated synthetic vision tests

Arabic names are **not automatically treated as equal to Latin MRZ transliterations**. They are reported as `not_comparable` unless a safe comparison is possible.

The repository and tests use synthetic data only. **Never commit real passport images, MRZ lines, passport numbers, or personal data.**

## API

### Scan a passport image or PDF

`POST /api/v1/passport/scan`

```bash
curl -X POST http://localhost:8000/api/v1/passport/scan \
  -H 'Accept: application/json' \
  -F 'passport=@passport.jpg'
```

A successful response includes vision metadata such as:

```json
{
  "scan": {
    "vision": {
      "strategy": "opencv_document_corners",
      "document_detected": true,
      "perspective_corrected": true,
      "quality": {
        "status": "accepted",
        "reasons": [],
        "blur_score": 143.8,
        "glare_ratio": 0.021
      },
      "diagnostics": {
        "status": "corrected",
        "detection_method": "contour_quad",
        "document_area_ratio": 0.61
      }
    },
    "smart_scanner": {
      "strategy": "imagemagick_adaptive_regions",
      "adaptive_mrz": {
        "enabled": true,
        "candidate_count": 5,
        "selected_candidate": 1,
        "detection_score": 35
      }
    }
  }
}
```

No corner coordinates, raw OCR text, or temporary file paths are returned.

A severely blurred or overexposed image can return:

```json
{
  "code": "low_image_quality",
  "quality": {
    "status": "rejected",
    "reasons": ["blur"]
  }
}
```

Warnings do not block scanning. Hard rejection is configurable with `PASSPORT_VISION_REJECT_LOW_QUALITY`.

### Parse MRZ text directly

`POST /api/v1/passport/mrz/parse`

```json
{
  "line1": "P<LBYALGAIDI<<AYA<<<<<<<<<<<<<<<<<<<<<<<<<<<",
  "line2": "1234567897LBY9501016F3001019<<<<<<<<<<<<<<02"
}
```

The example is fictional/synthetic test data.

### Other endpoints

- `POST /api/v1/passport/mrz/validate`
- `GET /api/v1/meta`
- `GET /health`
- `GET /openapi.yaml`

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

### 3. OpenCV vision environment

Use an isolated Python environment for the server-side vision worker:

```bash
python3 -m venv .venv-vision
.venv-vision/bin/python -m pip install --upgrade pip
.venv-vision/bin/python -m pip install -r requirements-vision.txt
```

Then configure:

```env
PASSPORT_VISION_ENABLED=true
PASSPORT_VISION_PYTHON_BINARY=/absolute/path/to/project/.venv-vision/bin/python
PASSPORT_VISION_REJECT_LOW_QUALITY=true
```

If the vision dependency is unavailable, scanning falls back to the existing privacy-first adaptive scanner instead of failing only because OpenCV is missing.

### 4. Run

```bash
php artisan serve
```

## Quality thresholds

The defaults are conservative starting points and should be calibrated against private synthetic/redacted evaluation data for the deployment camera workflow:

```env
PASSPORT_VISION_MIN_DOCUMENT_AREA=0.20
PASSPORT_VISION_BLUR_WARNING=75
PASSPORT_VISION_BLUR_REJECT=35
PASSPORT_VISION_GLARE_WARNING=0.18
PASSPORT_VISION_GLARE_REJECT=0.35
```

`warning` continues scanning. `rejected` returns HTTP 422 when hard rejection is enabled.

## Privacy lifecycle

For `/passport/scan` the server:

1. validates the upload and copies it to random private temporary storage;
2. rasterizes only page 1 for PDFs;
3. optionally detects the document boundary locally with OpenCV;
4. creates a private perspective-corrected frame when four corners are confidently detected;
5. computes blur/glare quality metrics locally;
6. rejects only severe low-quality input when configured to do so;
7. generates private normalized and MRZ/visual-zone crops;
8. runs local OCR across adaptive MRZ candidates and selects the strongest ICAO result;
9. parses/validates MRZ data and compares it with visible Arabic/English fields;
10. deletes the upload, PDF raster, corrected image, normalized image, and every crop in a `finally` cleanup before returning.

The application does not create passport database records. Full MRZ strings, raw OCR/TSV output, corrected images, and temporary paths must not be logged.

See [SECURITY.md](SECURITY.md).

## Roadmap

### v0.3.x

- stronger shadow and localized glare correction
- private camera/layout calibration tooling
- optional low-confidence visible-field suppression
- capture guidance metadata for mobile clients
- benchmark reporting from synthetic/redacted evaluation sets

### Later

- Flutter live camera scanner with framing guidance
- optional on-device OCR/vision
- ePassport/NFC research only where technically and legally appropriate

## License

MIT © 2026 Aya Aljaidi
