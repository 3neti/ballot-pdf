<?php

namespace LBHurtado\BallotPdf\Contracts;

use LBHurtado\BallotPdf\Domain\Evidence\EvidenceManifest;

interface WormStore
{
    public function commitPdf(string $serial, string $pdfBytes): string;

    public function commitManifest(string $serial, EvidenceManifest $manifest): string;

    /** @return iterable<string> */
    public function pdfs(): iterable;
}
