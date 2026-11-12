<?php

namespace LBHurtado\BallotPdf\Contracts;

interface PdfSignatureVerifier
{
    /** @param array{algorithm: string, key_id: string, signature: string} $signature */
    public function verify(string $pdfBytes, string $electionId, string $precinctId, array $signature): bool;
}
