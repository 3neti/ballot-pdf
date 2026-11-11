<?php

namespace LBHurtado\BallotPdf\Domain\Stock;

use LBHurtado\BallotPdf\Domain\Context\ElectionContext;
use LBHurtado\BallotPdf\Support\CanonicalJson;

final readonly class StockManifest
{
    /** @param list<BallotStock> $stocks */
    public function __construct(public ElectionContext $context, public array $stocks)
    {
        $serials = array_map(static fn (BallotStock $stock): string => $stock->serial->value, $stocks);
        if (count(array_unique($serials)) !== count($serials)) {
            throw new \InvalidArgumentException('Stock manifest contains duplicate serials.');
        }
        foreach ($stocks as $stock) {
            if ($stock->context->toArray() !== $context->toArray()) {
                throw new \InvalidArgumentException('Stock manifest contains a foreign election context.');
            }
        }
    }

    public function hash(): string
    {
        return hash('sha256', CanonicalJson::encode($this->toArray()));
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'context' => $this->context->toArray(),
            'stocks' => array_map(static fn (BallotStock $stock): array => $stock->toPayload(), $this->stocks),
        ];
    }
}
