<?php

namespace App\Services\CivilRegistry;

final class CivilRegistryVerificationService
{
    public function __construct(
        private readonly CivilRegistryVisualVerifierInterface $visualVerifier,
        private readonly CivilRegistryTemplateVerifier $templateVerifier,
    ) {}

    public function verify(
        string $imagePath,
        string $documentType,
        string $ocrText,
        array $extracted,
    ): array {
        if (! config('passport.civil_registry.verification_enabled', true)) {
            return $this->disabled();
        }

        $visual = $this->visualVerifier->verify($imagePath, $documentType);
        $template = $this->templateVerifier->verify($documentType, $ocrText, $visual);
        $fieldConsistency = $this->fieldConsistency($extracted);
        $score = $this->overallScore($template, $visual, $fieldConsistency);

        return [
            'status' => match (true) {
                $score === null => 'insufficient_evidence',
                $score >= 0.72 => 'signals_consistent',
                $score >= 0.48 => 'partial_signals',
                default => 'review_recommended',
            },
            'signal_score' => $score,
            'authenticity_verified' => false,
            'issuer_verification' => [
                'status' => 'not_configured',
                'database_checked' => false,
                'digital_signature_verified' => false,
            ],
            'qr' => $visual['qr'] ?? [
                'detected' => false,
                'decoded' => false,
                'issuer_lookup_performed' => false,
            ],
            'seals' => $visual['seals'] ?? [
                'detected' => false,
                'candidate_count' => 0,
                'candidates' => [],
            ],
            'template' => $template,
            'field_consistency' => $fieldConsistency,
            'notes' => [
                'QR validation is structural and positional only until an issuer verification service is configured.',
                'Seal detection is a visual signal and does not prove that a stamp is genuine.',
                'Template checks are calibrated from supported sample layouts and are not an official Civil Registry template certification.',
            ],
        ];
    }

    private function fieldConsistency(array $extracted): array
    {
        $fields = is_array($extracted['fields'] ?? null) ? $extracted['fields'] : [];
        $members = is_array($extracted['family_members'] ?? null) ? $extracted['family_members'] : [];
        $checks = [];

        if (isset($fields['national_number'])) {
            $checks['national_number_format'] = preg_match('/^\d{12}$/', (string) $fields['national_number']) === 1;
        }

        foreach (['date_of_birth', 'registered_since'] as $field) {
            if (isset($fields[$field])) {
                $checks[$field.'_format'] = $this->validIsoDate((string) $fields[$field]);
            }
        }

        $memberNationalNumbers = [];
        $validMemberNumbers = 0;
        $validMemberDates = 0;
        $memberDatesPresent = 0;

        foreach ($members as $member) {
            if (! is_array($member)) {
                continue;
            }

            if (isset($member['national_number'])) {
                $number = (string) $member['national_number'];
                $memberNationalNumbers[] = $number;
                if (preg_match('/^\d{12}$/', $number) === 1) {
                    $validMemberNumbers++;
                }
            }

            if (isset($member['date_of_birth'])) {
                $memberDatesPresent++;
                if ($this->validIsoDate((string) $member['date_of_birth'])) {
                    $validMemberDates++;
                }
            }
        }

        if ($memberNationalNumbers !== []) {
            $checks['family_member_national_numbers'] = $validMemberNumbers === count($memberNationalNumbers);
            $checks['family_member_national_numbers_unique'] = count(array_unique($memberNationalNumbers)) === count($memberNationalNumbers);
        }

        if ($memberDatesPresent > 0) {
            $checks['family_member_dates'] = $validMemberDates === $memberDatesPresent;
        }

        if ($checks === []) {
            return [
                'status' => 'not_evaluated',
                'score' => null,
                'checks' => [],
            ];
        }

        $passed = count(array_filter($checks, static fn (bool $value): bool => $value));
        $score = $passed / count($checks);

        return [
            'status' => $score === 1.0 ? 'consistent' : ($score >= 0.5 ? 'partial' : 'review_recommended'),
            'score' => round($score, 3),
            'checks' => $checks,
        ];
    }

    private function validIsoDate(string $value): bool
    {
        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $match)) {
            return false;
        }

        return checkdate((int) $match[2], (int) $match[3], (int) $match[1]);
    }

    private function overallScore(array $template, array $visual, array $fieldConsistency): ?float
    {
        $components = [];

        if (is_numeric($template['score'] ?? null)) {
            $components[] = [
                'weight' => 0.55,
                'score' => (float) $template['score'],
            ];
        }

        if (($visual['status'] ?? null) === 'processed'
            && is_numeric($visual['visual_signal_score'] ?? null)) {
            $components[] = [
                'weight' => 0.25,
                'score' => (float) $visual['visual_signal_score'],
            ];
        }

        if (is_numeric($fieldConsistency['score'] ?? null)) {
            $components[] = [
                'weight' => 0.20,
                'score' => (float) $fieldConsistency['score'],
            ];
        }

        if ($components === []) {
            return null;
        }

        $weight = array_sum(array_column($components, 'weight'));
        $weighted = array_sum(array_map(
            static fn (array $component): float => $component['weight'] * $component['score'],
            $components,
        ));

        return round(max(0.0, min(1.0, $weighted / $weight)), 3);
    }

    private function disabled(): array
    {
        return [
            'status' => 'disabled',
            'signal_score' => null,
            'authenticity_verified' => false,
            'issuer_verification' => [
                'status' => 'not_configured',
                'database_checked' => false,
                'digital_signature_verified' => false,
            ],
            'qr' => [
                'detected' => false,
                'decoded' => false,
                'issuer_lookup_performed' => false,
            ],
            'seals' => [
                'detected' => false,
                'candidate_count' => 0,
                'candidates' => [],
            ],
            'template' => [
                'status' => 'not_evaluated',
                'score' => null,
                'official_template_verified' => false,
            ],
            'field_consistency' => [
                'status' => 'not_evaluated',
                'score' => null,
                'checks' => [],
            ],
        ];
    }
}
