<?php

namespace LBHurtado\BallotPdf\Infrastructure\Signing;

use LBHurtado\BallotPdf\Contracts\RecordVerifier;

final class DeterministicRecordVerifier implements RecordVerifier
{
    public function __construct(private readonly string $keyId = 'reference-record-key') {}

    public function verify(string $purpose, string $electionId, string $precinctId, string $bytes, array $signature): bool
    {
        return ($signature['key_id'] ?? null) === $this->keyId
            && hash_equals(
                (string) ($signature['signature'] ?? ''),
                hash('sha256', $this->keyId.'|'.$purpose.'|'.$electionId.'|'.$precinctId.'|'.$bytes),
            );
    }
}
