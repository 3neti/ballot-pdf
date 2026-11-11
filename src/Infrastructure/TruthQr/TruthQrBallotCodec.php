<?php

namespace LBHurtado\BallotPdf\Infrastructure\TruthQr;

use LBHurtado\BallotPdf\Contracts\BallotPayloadCodec;
use LBHurtado\TruthQr\Core\TruthQr;

final readonly class TruthQrBallotCodec implements BallotPayloadCodec
{
    public function __construct(private TruthQr $truthQr) {}

    /** @param array<string, mixed> $payload @return list<string> */
    public function encode(array $payload): array
    {
        return $this->truthQr->encode('aes-election-v2', 'ballot', $payload);
    }

    /** @param list<string> $scans @return array<string, mixed> */
    public function decode(array $scans): array
    {
        return $this->truthQr->decode('aes-election-v2', $scans)['record'];
    }
}
