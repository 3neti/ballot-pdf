<?php

namespace LBHurtado\BallotPdf\Contracts;

interface RecordSigner
{
    /** @return array{key_id: string, signature: string} */
    public function sign(string $purpose, string $electionId, string $precinctId, string $bytes): array;
}
