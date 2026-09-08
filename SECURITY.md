# Security & Privacy

Libya Passport Reader handles identity-document data, so privacy is a product requirement, not an optional feature.

## Current guarantees

- The v0.1 MRZ endpoints accept MRZ text only; they do not accept passport images.
- Passport data is not persisted by the application.
- Application code must not log full MRZ lines or full passport numbers.
- Future image/OCR endpoints must process uploads in temporary storage and delete them immediately after processing unless an explicitly documented, lawful retention mode is introduced.

## Reporting

Please report security issues privately to the repository owner rather than opening a public issue containing passport data.

Never include real passport numbers, MRZ lines, passport images, or other personal data in bug reports.
