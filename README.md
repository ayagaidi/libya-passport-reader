# Libya Passport Reader 🇱🇾

**Privacy-first Laravel API for reading and validating passport MRZ data.**

Libya Passport Reader is an open-source developer project focused on turning machine-readable passport data into structured, validated JSON. The first release targets the two-line **TD3 MRZ** defined by ICAO Doc 9303 and provides a Libya-focused workflow without hard-coding unverified passport-specific assumptions.

> This is an independent open-source project. It is not an official Libyan government service and is not presented as being approved, endorsed, or affiliated with Libyan passport authorities.

## v0.1 foundation

- TD3 MRZ parsing
- ICAO check-digit validation
- Structured passport JSON
- Libya issuing-country detection (`LBY`)
- Input validation and rate limiting
- OpenAPI 3.1 contract
- Automated Laravel tests + Pint CI
- No passport-image storage
- No passport-data persistence

## API

### Parse MRZ

`POST /api/v1/passport/mrz/parse`

```json
{
  "line1": "P<LBYALGAIDI<<AYA<<<<<<<<<<<<<<<<<<<<<<<<<<<",
  "line2": "1234567897LBY9501016F3001019<<<<<<<<<<<<<<02"
}
```

The example above is synthetic test data. Do not use real passport data in issues, tests, screenshots, or documentation.

### Validate MRZ

`POST /api/v1/passport/mrz/validate`

Returns the passport-number, date-of-birth, expiry-date, optional personal-number, and composite check-digit results.

### Metadata

`GET /api/v1/meta`

### Health

`GET /health`

### OpenAPI

`GET /openapi.yaml`

## Local setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan serve
```

Then visit `http://localhost:8000/health`.

## Privacy model

The v0.1 API accepts **MRZ text only**. It does not accept or retain passport images and it does not persist parsed passport records.

Future OCR/image scanning work must use temporary processing and immediate deletion by default. Full MRZ strings and full passport numbers must not be written to application logs.

See [SECURITY.md](SECURITY.md).

## Roadmap

### v0.2 — OCR scanning
- image upload/camera input
- MRZ-region detection
- pluggable OCR engine
- temporary-file cleanup
- OCR confidence and MRZ cross-validation

### v0.3 — Visual-zone fields
- Arabic/English visual-zone extraction where reliably detectable
- compare OCR-visible fields with MRZ fields
- mismatch/confidence reporting

### Later
- Flutter camera scanner
- optional on-device processing
- ePassport/NFC research only where technically and legally appropriate

## Standards

The parser is designed around ICAO Doc 9303 TD3 MRZ structure and check-digit rules. The project does not claim document authenticity verification: a mathematically valid MRZ is not proof that a passport is genuine.

## License

MIT © 2026 Aya Aljaidi
