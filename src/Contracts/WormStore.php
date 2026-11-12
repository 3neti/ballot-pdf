<?php

namespace LBHurtado\BallotPdf\Contracts;

use LBHurtado\BallotPdf\Domain\Evidence\EvidenceManifest;
use LBHurtado\BallotPdf\Domain\Evidence\ElectionCloseCertificate;

interface WormStore
{
    public function commitPdf(string $serial, string $pdfBytes): string;

    public function commitManifest(string $serial, EvidenceManifest $manifest): string;

    public function commitCloseCertificate(ElectionCloseCertificate $certificate): string;

    public function pdf(string $serial): ?string;

    public function manifest(string $serial): ?EvidenceManifest;

    /** @return iterable<string> */
    public function pdfs(): iterable;
}
