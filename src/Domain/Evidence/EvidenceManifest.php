<?php

namespace LBHurtado\BallotPdf\Domain\Evidence;

use LBHurtado\BallotPdf\Support\CanonicalJson;

final readonly class EvidenceManifest
{
    /** @param array<string, mixed> $fields */
    public function __construct(public array $fields)
    {
        if (! isset($fields['ballot_serial'], $fields['pdf_sha256'])) {
            throw new \InvalidArgumentException('Evidence manifest requires a serial and PDF hash.');
        }
    }

    public function hash(): string
    {
        return hash('sha256', CanonicalJson::encode($this->fields));
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->fields;
    }
}
