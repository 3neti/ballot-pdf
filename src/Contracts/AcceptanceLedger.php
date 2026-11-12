<?php

namespace LBHurtado\BallotPdf\Contracts;

use LBHurtado\BallotPdf\Domain\Evidence\AcceptanceRecord;
use LBHurtado\BallotPdf\Domain\Evidence\ElectionCloseCertificate;

interface AcceptanceLedger
{
    public function accept(AcceptanceRecord $record): void;

    public function byIdempotency(string $idempotencyKey): ?AcceptanceRecord;

    public function bySerial(string $serial): ?AcceptanceRecord;

    public function allocateSequence(): int;

    /** @return list<AcceptanceRecord> */
    public function records(): array;

    public function close(ElectionCloseCertificate $certificate): void;

    public function isClosed(): bool;

    /** @return array<string, mixed> */
    public function recoveryReport(): array;
}
