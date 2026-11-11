<?php

namespace LBHurtado\BallotPdf\Contracts;

interface QrImageRenderer
{
    public function render(string $payload): string;
}
