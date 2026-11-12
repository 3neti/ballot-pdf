<?php

namespace LBHurtado\BallotPdf\Infrastructure\Audit;

use LBHurtado\BallotPdf\Contracts\PrintAdapter;

final class ReferencePrintAdapter implements PrintAdapter
{
    public function print(string $serial, string $bytes): array
    {
        return ['status' => 'printed', 'printer_id' => 'reference-a4-printer', 'job_id' => 'print-'.$serial];
    }
}
