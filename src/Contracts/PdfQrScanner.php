<?php

namespace LBHurtado\BallotPdf\Contracts;

interface PdfQrScanner
{
    /** @return list<string> */
    public function scans(string $pdfPath): array;
}
