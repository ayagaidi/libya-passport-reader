<?php

namespace App\DTO;

final readonly class PassportData
{
    public function __construct(
        public string $documentType,
        public string $issuingCountry,
        public string $surname,
        public array $givenNames,
        public string $passportNumber,
        public string $nationality,
        public ?string $dateOfBirth,
        public string $sex,
        public ?string $expiryDate,
        public ?string $personalNumber,
        public bool $isLibyanPassport,
    ) {}

    public function toArray(): array
    {
        return [
            'document_type' => $this->documentType,
            'issuing_country' => $this->issuingCountry,
            'surname' => $this->surname,
            'given_names' => $this->givenNames,
            'passport_number' => $this->passportNumber,
            'nationality' => $this->nationality,
            'date_of_birth' => $this->dateOfBirth,
            'sex' => $this->sex,
            'expiry_date' => $this->expiryDate,
            'personal_number' => $this->personalNumber,
            'is_libyan_passport' => $this->isLibyanPassport,
        ];
    }
}
