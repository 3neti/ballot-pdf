<?php

namespace LBHurtado\BallotPdf\Contracts;

interface AuditReadback
{
    /** @return list<string> */
    public function scans(string $serial, string $bytes): array;
}
