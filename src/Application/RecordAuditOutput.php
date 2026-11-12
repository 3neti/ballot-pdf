<?php

namespace LBHurtado\BallotPdf\Application;

use LBHurtado\BallotPdf\Contracts\AuditOutputRenderer;
use LBHurtado\BallotPdf\Contracts\AuditReadback;
use LBHurtado\BallotPdf\Contracts\BallotPayloadCodec;
use LBHurtado\BallotPdf\Contracts\PrintAdapter;
use LBHurtado\BallotPdf\Domain\Ballot\FinalizedBallot;
use LBHurtado\BallotPdf\Domain\Evidence\AcceptanceRecord;
use LBHurtado\BallotPdf\Domain\Evidence\AuditRecord;

final readonly class RecordAuditOutput
{
    public function __construct(
        private AuditOutputRenderer $renderer,
        private PrintAdapter $printer,
        private AuditReadback $readback,
        private BallotPayloadCodec $codec,
    ) {}

    /** @param array<string, mixed> $custody @return array{record: AuditRecord, bytes: string} */
    public function execute(
        FinalizedBallot $ballot,
        AcceptanceRecord $acceptance,
        string $inspection = 'pending',
        array $custody = [],
    ): array {
        $bytes = $this->renderer->render($ballot, $acceptance);
        $print = $this->printer->print($ballot->serial, $bytes);
        $readback = $this->readback->scans($ballot->serial, $bytes);
        $issues = [];
        try {
            $expected = $this->codec->decode($ballot->truthQrScans);
            $actual = $this->codec->decode($readback);
            if ($expected !== $actual) {
                $issues[] = 'A4 Truth QR read-back does not match the accepted payload.';
            }
        } catch (\Throwable $exception) {
            $issues[] = 'A4 Truth QR read-back is invalid: '.$exception->getMessage();
        }
        if (($print['status'] ?? '') !== 'printed') {
            $issues[] = 'A4 print was not acknowledged as printed.';
        }
        $disposition = $issues === [] ? 'verified' : 'mismatch';
        $record = new AuditRecord(
            $ballot->serial,
            $acceptance->acceptanceId,
            hash('sha256', $bytes),
            $readback,
            $print,
            $inspection,
            $disposition,
            $custody,
            $issues,
        );
        return ['record' => $record, 'bytes' => $bytes];
    }
}
