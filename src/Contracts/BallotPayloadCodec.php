<?php

namespace LBHurtado\BallotPdf\Contracts;

interface BallotPayloadCodec
{
    /** @param array<string, mixed> $payload @return list<string> */
    public function encode(array $payload): array;

    /** @param list<string> $scans @return array<string, mixed> */
    public function decode(array $scans): array;
}
