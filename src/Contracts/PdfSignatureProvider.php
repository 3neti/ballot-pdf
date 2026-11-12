<?php

namespace LBHurtado\BallotPdf\Contracts;

interface PdfSignatureProvider
{
    /**
     * Sign and, where supported by the adapter, embed a PDF-native signature.
     *
     * @return array{pdf_bytes: string, algorithm: string, key_id: string, signature: string}
     */
    public function sign(string $pdfBytes, string $electionId, string $precinctId): array;
}
