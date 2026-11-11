<?php

namespace LBHurtado\BallotPdf\Contracts;

interface PdfMaterializer
{
    /** @param array<string, mixed> $payload @param list<string> $truthQrScans */
    public function materialize(array $payload, array $truthQrScans): string;
}
