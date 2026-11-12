<?php

namespace LBHurtado\BallotPdf\Domain\Evidence;

use LBHurtado\BallotPdf\Support\CanonicalJson;

final readonly class AuditRecord
{
    /** @param list<string> $readbackScans @param array<string, mixed> $printResult @param list<array<string, mixed>> $custody */
    public function __construct(
        public string $serial,
        public string $acceptanceId,
        public string $outputSha256,
        public array $readbackScans,
        public array $printResult,
        public string $inspection,
        public string $disposition,
        public array $custody = [],
        public array $issues = [],
    ) {}

    public function hash(): string
    {
        return hash('sha256', CanonicalJson::encode($this->toArray()));
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'artifact_type' => 'a4-audit-record',
            'ballot_serial' => $this->serial,
            'acceptance_id' => $this->acceptanceId,
            'output_sha256' => $this->outputSha256,
            'readback_scans' => $this->readbackScans,
            'print_result' => $this->printResult,
            'inspection' => $this->inspection,
            'disposition' => $this->disposition,
            'custody' => $this->custody,
            'issues' => $this->issues,
        ];
    }
}
