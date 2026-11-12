<?php

namespace LBHurtado\BallotPdf\Infrastructure\Audit;

use LBHurtado\BallotPdf\Contracts\AuditOutputRenderer;
use LBHurtado\BallotPdf\Domain\Ballot\FinalizedBallot;
use LBHurtado\BallotPdf\Domain\Evidence\AcceptanceRecord;

final class ReferenceAuditOutputRenderer implements AuditOutputRenderer
{
    public function render(FinalizedBallot $ballot, AcceptanceRecord $acceptance): string
    {
        return "A4 AUDIT OUTPUT\n"
            .'BALLOT SERIAL: '.$ballot->serial."\n"
            .'ACCEPTANCE: '.$acceptance->acceptanceId."\n"
            .'PDF SHA256: '.hash('sha256', $ballot->pdfBytes)."\n"
            .'TRUTH QR: '.implode('|', $ballot->truthQrScans)."\n";
    }
}
