<?php

namespace LBHurtado\BallotPdf\Application;

use LBHurtado\BallotPdf\Support\CanonicalJson;

final readonly class RosterDistribution
{
    /** @param list<string> $recipients */
    public function create(string $rosterVersion, string $rosterHash, array $recipients, string $channel, string $distributedAt, string $approvalHash): array
    {
        $record = [
            'artifact_type' => 'trust-roster-distribution',
            'artifact_version' => '1.0',
            'roster_version' => $rosterVersion,
            'roster_sha256' => $rosterHash,
            'recipients' => array_values($recipients),
            'channel' => $channel,
            'distributed_at' => $distributedAt,
            'approval_sha256' => $approvalHash,
        ];
        return [...$record, 'record_sha256' => hash('sha256', CanonicalJson::encode($record))];
    }

    /** @param array<string, mixed> $record */
    public function verify(array $record, string $rosterHash): bool
    {
        $unsigned = $record;
        unset($unsigned['record_sha256']);
        return ($record['roster_sha256'] ?? '') === $rosterHash
            && ($record['record_sha256'] ?? '') === hash('sha256', CanonicalJson::encode($unsigned));
    }
}
