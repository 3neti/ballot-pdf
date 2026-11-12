<?php

namespace LBHurtado\BallotPdf\Application;

use LBHurtado\BallotPdf\Support\CanonicalJson;

final class ValidateAcceptanceManifest
{
    /** @param array<string, mixed> $manifest */
    public function execute(array $manifest): void
    {
        foreach (['schema', 'ballot_serial', 'pdf_sha256', 'payload_sha256', 'truth_qr_scans', 'pdf_signature', 'context', 'acceptance_id', 'idempotency_key', 'content_fingerprint', 'acceptance_sequence', 'status', 'manifest_signature'] as $field) {
            if (! array_key_exists($field, $manifest)) {
                throw new \InvalidArgumentException("Acceptance manifest requires {$field}.");
            }
        }
        if ($manifest['schema'] !== 'ballot-pdf.acceptance-manifest.v1') {
            throw new \InvalidArgumentException('Unsupported acceptance manifest schema.');
        }
        foreach (['pdf_sha256', 'payload_sha256', 'content_fingerprint'] as $hash) {
            if (! is_string($manifest[$hash]) || ! preg_match('/^[a-f0-9]{64}$/', $manifest[$hash])) {
                throw new \InvalidArgumentException("Acceptance manifest {$hash} must be a SHA-256 hash.");
            }
        }
        if (! is_array($manifest['truth_qr_scans']) || $manifest['truth_qr_scans'] === []) {
            throw new \InvalidArgumentException('Acceptance manifest requires Truth QR scans.');
        }
        if (! is_array($manifest['context']) || trim((string) ($manifest['context']['election_id'] ?? '')) === '' || trim((string) ($manifest['context']['precinct_id'] ?? '')) === '') {
            throw new \InvalidArgumentException('Acceptance manifest context is incomplete.');
        }
        if (! is_array($manifest['pdf_signature']) || trim((string) ($manifest['pdf_signature']['key_id'] ?? '')) === '' || trim((string) ($manifest['pdf_signature']['signature'] ?? '')) === '') {
            throw new \InvalidArgumentException('Acceptance manifest PDF signature is incomplete.');
        }
        if (! is_array($manifest['manifest_signature']) || trim((string) ($manifest['manifest_signature']['key_id'] ?? '')) === '' || trim((string) ($manifest['manifest_signature']['signature'] ?? '')) === '') {
            throw new \InvalidArgumentException('Acceptance manifest detached signature is incomplete.');
        }
        if ((int) $manifest['acceptance_sequence'] < 1) {
            throw new \InvalidArgumentException('Acceptance manifest sequence must be positive.');
        }
        if (! is_array($manifest['artifact_refs'])) {
            throw new \InvalidArgumentException('Acceptance manifest artifact references are required.');
        }
    }

    /** @param array<string, mixed> $manifest @return array<string, mixed> */
    public function unsigned(array $manifest): array
    {
        $unsigned = $manifest;
        unset($unsigned['manifest_signature']);
        return $unsigned;
    }
}
