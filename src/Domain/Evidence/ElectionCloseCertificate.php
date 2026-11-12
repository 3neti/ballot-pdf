<?php

namespace LBHurtado\BallotPdf\Domain\Evidence;

use LBHurtado\BallotPdf\Support\CanonicalJson;

final readonly class ElectionCloseCertificate
{
    /** @param list<string> $acceptedSerials @param array<string, mixed> $reconciliation */
    public function __construct(
        public string $electionId,
        public string $scope,
        public string $closedAt,
        public int $lastAcceptanceSequence,
        public array $acceptedSerials,
        public string $castSetCommitment,
        public string $tallyRulesHash,
        public string $trustRosterHash,
        public array $reconciliation,
        public string $certificateId,
        public array $signature = [],
    ) {}

    public function hash(): string
    {
        return hash('sha256', CanonicalJson::encode($this->toArray()));
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'certificate_id' => $this->certificateId,
            'election_id' => $this->electionId,
            'scope' => $this->scope,
            'closed_at' => $this->closedAt,
            'last_acceptance_sequence' => $this->lastAcceptanceSequence,
            'accepted_ballot_count' => count($this->acceptedSerials),
            'accepted_serials' => $this->acceptedSerials,
            'cast_set_commitment' => $this->castSetCommitment,
            'tally_rules_hash' => $this->tallyRulesHash,
            'trust_roster_hash' => $this->trustRosterHash,
            'reconciliation' => $this->reconciliation,
            'signature' => $this->signature,
        ];
    }
}
