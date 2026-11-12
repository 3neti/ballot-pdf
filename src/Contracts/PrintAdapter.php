<?php

namespace LBHurtado\BallotPdf\Contracts;

interface PrintAdapter
{
    /** @return array{status: string, printer_id: string|null, job_id: string|null} */
    public function print(string $serial, string $bytes): array;
}
