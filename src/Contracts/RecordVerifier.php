<?php

namespace LBHurtado\BallotPdf\Contracts;

interface RecordVerifier
{
    /** @param array{key_id: string, signature: string} $signature */
    public function verify(string $purpose, string $electionId, string $precinctId, string $bytes, array $signature): bool;
}
