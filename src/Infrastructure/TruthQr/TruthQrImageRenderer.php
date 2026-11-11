<?php

namespace LBHurtado\BallotPdf\Infrastructure\TruthQr;

use LBHurtado\BallotPdf\Contracts\QrImageRenderer;
use LBHurtado\TruthQr\Contracts\QrImageWriter;

final readonly class TruthQrImageRenderer implements QrImageRenderer
{
    public function __construct(private QrImageWriter $writer) {}

    public function render(string $payload): string
    {
        return $this->writer->renderPng($payload, true);
    }
}
