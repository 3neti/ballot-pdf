<?php

namespace LBHurtado\BallotPdf\Contracts;

use LBHurtado\BallotPdf\Domain\Stock\BallotStock;

interface BallotStockRepository
{
    public function reserve(string $serial): BallotStock;
}
