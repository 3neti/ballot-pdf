<?php

namespace LBHurtado\BallotPdf\Domain\Ballot;

use LBHurtado\BallotPdf\Support\CanonicalJson;

final readonly class FinalizedBallot
{
    /** @param array<string, mixed> $payload @param list<string> $truthQrScans */
    public function __construct(
        public string $serial,
        public string $pdfBytes,
        public array $payload,
        public array $truthQrScans,
        public string $pdfSha256,
    ) {}

    /** @return array<string, mixed> */
    public function evidence(): array
    {
        return [
            'ballot_serial' => $this->serial,
            'pdf_sha256' => $this->pdfSha256,
            'payload_sha256' => hash('sha256', CanonicalJson::encode($this->payload)),
            'truth_qr_scans' => $this->truthQrScans,
            'status' => 'finalized',
        ];
    }
}
