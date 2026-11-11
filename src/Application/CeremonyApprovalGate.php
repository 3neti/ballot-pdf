<?php

namespace LBHurtado\BallotPdf\Application;

use LBHurtado\BallotPdf\Support\CanonicalJson;

final readonly class CeremonyApprovalGate
{
    /** @param list<array{approver_id: string, role: string, decision: string, approved_at: string}> $approvals */
    public function requireQuorum(array $approvals, int $quorum = 2): string
    {
        $approved = array_values(array_filter($approvals, static fn (array $approval): bool => ($approval['decision'] ?? '') === 'approved'));
        $identities = array_values(array_unique(array_map(static fn (array $approval): string => (string) ($approval['approver_id'] ?? ''), $approved)));
        if (count($approved) < $quorum || count($identities) < $quorum) {
            throw new \RuntimeException("Key ceremony requires {$quorum} distinct approvals.");
        }
        return hash('sha256', CanonicalJson::encode($approved));
    }
}
