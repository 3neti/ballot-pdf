<?php

namespace LBHurtado\BallotPdf\Domain\Stock;

use LBHurtado\BallotPdf\Domain\Context\ElectionContext;

final readonly class BallotStock
{
    public function __construct(
        public BallotSerial $serial,
        public ElectionContext $context,
        public string $documentId,
        public string $documentHash,
        public string $assetBundleId,
        public string $assetBundleHash,
        public string $status = 'available',
    ) {
        foreach ([$documentId, $documentHash, $assetBundleId, $assetBundleHash] as $value) {
            if (trim($value) === '') {
                throw new \InvalidArgumentException('Stock document references must not be empty.');
            }
        }
    }

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            ...$this->context->toArray(),
            'paper_ballot_serial' => $this->serial->value,
            'document_profile' => [
                'type' => 'official-ballot',
                'id' => $this->documentId,
                'hash' => $this->documentHash,
                'asset_bundle_id' => $this->assetBundleId,
                'asset_bundle_hash' => $this->assetBundleHash,
            ],
        ];
    }
}
