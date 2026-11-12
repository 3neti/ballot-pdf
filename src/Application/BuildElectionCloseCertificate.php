<?php

namespace LBHurtado\BallotPdf\Application;

use LBHurtado\BallotPdf\Domain\Evidence\AcceptanceRecord;
use LBHurtado\BallotPdf\Domain\Evidence\ElectionCloseCertificate;
use LBHurtado\BallotPdf\Support\CanonicalJson;
use LBHurtado\BallotPdf\Contracts\RecordSigner;

final class BuildElectionCloseCertificate
{
    public function __construct(private readonly ?RecordSigner $signer = null) {}
    /** @param list<AcceptanceRecord> $accepted */
    public function execute(
        string $electionId,
        string $scope,
        string $closedAt,
        array $accepted,
        string $tallyRulesHash,
        string $trustRosterHash,
        array $reconciliation = [],
    ): ElectionCloseCertificate {
        usort($accepted, static fn (AcceptanceRecord $left, AcceptanceRecord $right): int => $left->serial <=> $right->serial);
        $serials = array_map(static fn (AcceptanceRecord $record): string => $record->serial, $accepted);
        $commitment = hash('sha256', CanonicalJson::encode(array_map(static fn (AcceptanceRecord $record): array => [
            'serial' => $record->serial,
            'pdf_sha256' => $record->pdfSha256,
            'manifest_sha256' => $record->manifestSha256,
            'acceptance_sequence' => $record->sequence,
        ], $accepted)));

        $unsigned = new ElectionCloseCertificate(
            $electionId,
            $scope,
            $closedAt,
            $accepted === [] ? 0 : max(array_map(static fn (AcceptanceRecord $record): int => $record->sequence, $accepted)),
            $serials,
            $commitment,
            $tallyRulesHash,
            $trustRosterHash,
            $reconciliation,
            hash('sha256', $electionId.'|'.$scope.'|'.$closedAt.'|'.$commitment),
        );
        if ($this->signer === null) {
            return $unsigned;
        }
        $signature = $this->signer->sign(
            'election-close-certificate',
            $electionId,
            $scope,
            CanonicalJson::encode($unsigned->toArray()),
        );
        return new ElectionCloseCertificate(
            $unsigned->electionId,
            $unsigned->scope,
            $unsigned->closedAt,
            $unsigned->lastAcceptanceSequence,
            $unsigned->acceptedSerials,
            $unsigned->castSetCommitment,
            $unsigned->tallyRulesHash,
            $unsigned->trustRosterHash,
            $unsigned->reconciliation,
            $unsigned->certificateId,
            $signature,
        );
    }
}
