<?php

namespace LBHurtado\BallotPdf\Infrastructure\Signing;

use LBHurtado\BallotPdf\Contracts\RecordSigner;

/** Reference-only signer for deterministic tests; not a production key implementation. */
final class DeterministicRecordSigner implements RecordSigner
{
    public function __construct(private readonly string $keyId = 'reference-record-key') {}

    public function sign(string $purpose, string $electionId, string $precinctId, string $bytes): array
    {
        return [
            'key_id' => $this->keyId,
            'signature' => hash('sha256', $this->keyId.'|'.$purpose.'|'.$electionId.'|'.$precinctId.'|'.$bytes),
        ];
    }
}
