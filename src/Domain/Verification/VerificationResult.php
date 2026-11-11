<?php

namespace LBHurtado\BallotPdf\Domain\Verification;

final readonly class VerificationResult
{
    /** @param list<string> $issues */
    public function __construct(
        public string $path,
        public string $serial,
        public string $classification,
        public bool $eligible,
        public array $issues = [],
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'path' => $this->path,
            'ballot_serial' => $this->serial,
            'classification' => $this->classification,
            'eligible' => $this->eligible,
            'issues' => $this->issues,
        ];
    }
}
