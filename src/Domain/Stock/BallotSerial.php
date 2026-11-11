<?php

namespace LBHurtado\BallotPdf\Domain\Stock;

final readonly class BallotSerial
{
    public function __construct(public string $value)
    {
        if (preg_match('/^[A-Za-z0-9._-]{1,128}$/D', $value) !== 1) {
            throw new \InvalidArgumentException('Ballot serial is malformed.');
        }
    }
}
