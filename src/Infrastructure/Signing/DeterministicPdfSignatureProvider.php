<?php

namespace LBHurtado\BallotPdf\Infrastructure\Signing;

use LBHurtado\BallotPdf\Contracts\PdfSignatureProvider;
use LBHurtado\BallotPdf\Contracts\PdfSignatureVerifier;

/** Reference-only provider for deterministic tests; not a production PDF signer. */
final class DeterministicPdfSignatureProvider implements PdfSignatureProvider, PdfSignatureVerifier
{
    public function __construct(private readonly string $keyId = 'reference-pdf-key') {}

    public function sign(string $pdfBytes, string $electionId, string $precinctId): array
    {
        $signature = hash('sha256', $this->keyId.'|'.$electionId.'|'.$precinctId.'|'.$pdfBytes);
        return [
            'pdf_bytes' => $pdfBytes."\n%PDF-SIGNATURE {$signature}",
            'algorithm' => 'reference-deterministic-pdf-signature',
            'key_id' => $this->keyId,
            'signature' => $signature,
        ];
    }

    public function verify(string $pdfBytes, string $electionId, string $precinctId, array $signature): bool
    {
        $marker = "\n%PDF-SIGNATURE ".(string) ($signature['signature'] ?? '');
        if (! str_ends_with($pdfBytes, $marker)) {
            return false;
        }
        $unsigned = substr($pdfBytes, 0, -strlen($marker));
        return hash_equals(
            (string) ($signature['signature'] ?? ''),
            hash('sha256', $this->keyId.'|'.$electionId.'|'.$precinctId.'|'.$unsigned),
        );
    }
}
