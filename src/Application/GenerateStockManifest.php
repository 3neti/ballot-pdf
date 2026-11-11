<?php

namespace LBHurtado\BallotPdf\Application;

use LBHurtado\BallotPdf\Domain\Context\ElectionContext;
use LBHurtado\BallotPdf\Domain\Stock\BallotStock;
use LBHurtado\BallotPdf\Domain\Stock\StockManifest;

final readonly class GenerateStockManifest
{
    /** @param list<BallotStock> $stocks */
    public function execute(ElectionContext $context, array $stocks): StockManifest
    {
        return new StockManifest($context, $stocks);
    }
}
