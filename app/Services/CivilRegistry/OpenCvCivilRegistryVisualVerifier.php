<?php

namespace App\Services\CivilRegistry;

use Symfony\Component\Process\Process;
use Throwable;

final class OpenCvCivilRegistryVisualVerifier implements CivilRegistryVisualVerifierInterface
{
    public function verify(string $imagePath, string $documentType): array
    {
        if (! config('passport.civil_registry.verification_enabled', true)) {
            return $this->unavailable('disabled');
        }

        $process = new Process([
            (string) config('passport.vision.python_binary', 'python3'),
            base_path('tools/civil_registry_verify.py'),
            $imagePath,
            $documentType,
            '--max-dimension',
            (string) config('passport.civil_registry.verification_max_dimension', 2200),
        ]);
        $process->setTimeout((float) config('passport.civil_registry.verification_timeout', 15));

        try {
            $process->run();
        } catch (Throwable) {
            return $this->unavailable('verification_process_unavailable');
        }

        $payload = json_decode(trim($process->getOutput()), true);

        if (! is_array($payload)) {
            return $this->unavailable('invalid_verification_response');
        }

        if (($payload['status'] ?? null) !== 'processed') {
            return $this->unavailable((string) ($payload['reason'] ?? 'verification_processing_failed'));
        }

        return [
            'status' => 'processed',
            'qr' => $this->qr(is_array($payload['qr'] ?? null) ? $payload['qr'] : []),
            'seals' => $this->seals(is_array($payload['seals'] ?? null) ? $payload['seals'] : []),
            'visual_signal_score' => $this->score($payload['visual_signal_score'] ?? null),
            'authenticity_verified' => false,
        ];
    }

    private function qr(array $qr): array
    {
        $result = [
            'detected' => (bool) ($qr['detected'] ?? false),
            'decoded' => (bool) ($qr['decoded'] ?? false),
            'payload_format' => isset($qr['payload_format']) ? (string) $qr['payload_format'] : null,
            'structure_valid' => isset($qr['structure_valid']) ? (bool) $qr['structure_valid'] : null,
            'expected_position' => 'top_right',
            'position_consistent' => isset($qr['position_consistent']) ? (bool) $qr['position_consistent'] : null,
            'issuer_lookup_performed' => false,
        ];

        if (($qr['payload_format'] ?? null) === 'civil_registry_check_number'
            && is_string($qr['check_number'] ?? null)) {
            $result['check_number'] = $qr['check_number'];
        }

        if (($qr['payload_format'] ?? null) === 'unrecognized'
            && is_string($qr['payload_sha256'] ?? null)) {
            $result['payload_sha256'] = $qr['payload_sha256'];
        }

        if (is_array($qr['location'] ?? null)) {
            $result['location'] = $qr['location'];
        }

        return $result;
    }

    private function seals(array $seals): array
    {
        $candidates = [];

        foreach (is_array($seals['candidates'] ?? null) ? $seals['candidates'] : [] as $candidate) {
            if (! is_array($candidate)) {
                continue;
            }

            $candidates[] = [
                'location' => isset($candidate['location']) ? (string) $candidate['location'] : 'other',
                'confidence' => $this->score($candidate['confidence'] ?? null),
                'box' => is_array($candidate['box'] ?? null) ? $candidate['box'] : null,
            ];
        }

        return [
            'detected' => (bool) ($seals['detected'] ?? false),
            'candidate_count' => count($candidates),
            'expected_locations' => array_values(array_filter(
                is_array($seals['expected_locations'] ?? null) ? $seals['expected_locations'] : [],
                'is_string',
            )),
            'expected_location_match' => isset($seals['expected_location_match'])
                ? (bool) $seals['expected_location_match']
                : null,
            'blue_ink_ratio' => isset($seals['blue_ink_ratio']) ? round((float) $seals['blue_ink_ratio'], 5) : null,
            'method' => 'blue_ink_connected_components',
            'candidates' => $candidates,
        ];
    }

    private function score(mixed $value): ?float
    {
        if (! is_numeric($value)) {
            return null;
        }

        return round(max(0.0, min(1.0, (float) $value)), 3);
    }

    private function unavailable(string $reason): array
    {
        return [
            'status' => 'unavailable',
            'reason' => $reason,
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
            'visual_signal_score' => null,
            'authenticity_verified' => false,
        ];
    }
}
