<?php

namespace LBHurtado\BallotPdf\Contracts;

use LBHurtado\BallotPdf\Domain\Evidence\AcceptanceRecord;

interface AcceptanceLedger
{
    public function accept(AcceptanceRecord $record): void;

    public function byIdempotency(string $idempotencyKey): ?AcceptanceRecord;

    public function bySerial(string $serial): ?AcceptanceRecord;
}
