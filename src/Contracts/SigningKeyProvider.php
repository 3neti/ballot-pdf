<?php

namespace LBHurtado\BallotPdf\Contracts;

interface SigningKeyProvider
{
    /** @return array{key_id: string, secret_key: string} */
    public function forContext(string $electionId, string $precinctId): array;
}
