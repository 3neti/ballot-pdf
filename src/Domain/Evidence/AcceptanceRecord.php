<?php

namespace LBHurtado\BallotPdf\Domain\Evidence;

use LBHurtado\BallotPdf\Support\CanonicalJson;

final readonly class AcceptanceRecord
{
    /** @param array<string, mixed> $context */
    public function __construct(
        public string $acceptanceId,
        public string $idempotencyKey,
        public string $serial,
        public string $pdfSha256,
        public string $payloadSha256,
        public string $manifestSha256,
        public string $acceptedAt,
        public int $sequence,
        public array $context,
        public string $status = 'accepted',
        public string $contentFingerprint = '',
    ) {
        foreach ([$acceptanceId, $idempotencyKey, $serial, $pdfSha256, $payloadSha256, $manifestSha256, $acceptedAt] as $value) {
            if (trim($value) === '') {
                throw new \InvalidArgumentException('Acceptance record fields must not be empty.');
            }
        }
        if ($sequence < 1) {
            throw new \InvalidArgumentException('Acceptance sequence must be positive.');
        }
    }

    public function hash(): string
    {
        return hash('sha256', CanonicalJson::encode($this->toArray()));
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'acceptance_id' => $this->acceptanceId,
            'idempotency_key' => $this->idempotencyKey,
            'ballot_serial' => $this->serial,
            'pdf_sha256' => $this->pdfSha256,
            'payload_sha256' => $this->payloadSha256,
            'manifest_sha256' => $this->manifestSha256,
            'accepted_at' => $this->acceptedAt,
            'acceptance_sequence' => $this->sequence,
            'context' => $this->context,
            'status' => $this->status,
            'content_fingerprint' => $this->contentFingerprint !== ''
                ? $this->contentFingerprint
                : hash('sha256', CanonicalJson::encode([
                    'ballot_serial' => $this->serial,
                    'pdf_sha256' => $this->pdfSha256,
                    'payload_sha256' => $this->payloadSha256,
                    'manifest_sha256' => $this->manifestSha256,
                    'context' => $this->context,
                ])),
        ];
    }
}
