<?php

namespace LBHurtado\BallotPdf\Application;

use LBHurtado\BallotPdf\Domain\Ballot\BallotSession;
use LBHurtado\BallotPdf\Contracts\BallotPayloadCodec;

final readonly class BuildSignedBallotPayload
{
    public function __construct(private BallotPayloadCodec $codec) {}

    /** @return list<string> */
    public function execute(BallotSession $session): array
    {
        $session->validate();
        $payload = [
            ...$session->stock->toPayload(),
            'candidate_codes' => array_values(array_merge(...array_values($session->selections()) ?: [[]])),
            'selections' => $session->selections(),
        ];
        return $this->codec->encode($payload);
    }
}
