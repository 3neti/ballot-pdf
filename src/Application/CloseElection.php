<?php

namespace LBHurtado\BallotPdf\Application;

use LBHurtado\BallotPdf\Contracts\AcceptanceLedger;
use LBHurtado\BallotPdf\Contracts\RecordSigner;
use LBHurtado\BallotPdf\Contracts\WormStore;
use LBHurtado\BallotPdf\Domain\Evidence\ElectionCloseCertificate;

final readonly class CloseElection
{
    public function __construct(
        private AcceptanceLedger $ledger,
        private WormStore $store,
        private RecordSigner $signer,
    ) {}

    /** @param array<string, mixed> $reconciliation */
    public function execute(
        string $electionId,
        string $scope,
        string $closedAt,
        string $tallyRulesHash,
        string $trustRosterHash,
        array $reconciliation = [],
    ): ElectionCloseCertificate {
        if ($this->ledger->isClosed()) {
            throw new \RuntimeException('Election is already closed.');
        }
        $certificate = (new BuildElectionCloseCertificate($this->signer))->execute(
            $electionId,
            $scope,
            $closedAt,
            $this->ledger->records(),
            $tallyRulesHash,
            $trustRosterHash,
            $reconciliation,
        );
        $this->store->commitCloseCertificate($certificate);
        $this->ledger->close($certificate);
        return $certificate;
    }
}
