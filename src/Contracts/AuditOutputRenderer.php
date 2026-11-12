<?php

namespace LBHurtado\BallotPdf\Contracts;

use LBHurtado\BallotPdf\Domain\Ballot\FinalizedBallot;
use LBHurtado\BallotPdf\Domain\Evidence\AcceptanceRecord;

interface AuditOutputRenderer
{
    public function render(FinalizedBallot $ballot, AcceptanceRecord $acceptance): string;
}
