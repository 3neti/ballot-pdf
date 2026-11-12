<?php

namespace LBHurtado\BallotPdf\Infrastructure\Audit;

use LBHurtado\BallotPdf\Contracts\AuditReadback;

final class ReferenceAuditReadback implements AuditReadback
{
    /** @param list<string> $scans */
    public function __construct(private readonly array $scans) {}

    public function scans(string $serial, string $bytes): array
    {
        return $this->scans;
    }
}
