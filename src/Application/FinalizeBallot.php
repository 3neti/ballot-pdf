<?php

namespace LBHurtado\BallotPdf\Application;

use LBHurtado\BallotPdf\Contracts\BallotPayloadCodec;
use LBHurtado\BallotPdf\Contracts\PdfMaterializer;
use LBHurtado\BallotPdf\Contracts\WormStore;
use LBHurtado\BallotPdf\Domain\Ballot\BallotSession;
use LBHurtado\BallotPdf\Domain\Ballot\FinalizedBallot;
use LBHurtado\BallotPdf\Domain\Evidence\EvidenceManifest;

final readonly class FinalizeBallot
{
    public function __construct(
        private BallotPayloadCodec $codec,
        private PdfMaterializer $materializer,
        private WormStore $store,
    ) {}

    public function execute(BallotSession $session): FinalizedBallot
    {
        $session->validate();
        $payload = [
            ...$session->stock->toPayload(),
            'candidate_codes' => array_values(array_merge(...array_values($session->selections()) ?: [[]])),
            'selections' => $session->selections(),
        ];
        $scans = $this->codec->encode($payload);
        $pdfBytes = $this->materializer->materialize($payload, $scans);
        $final = new FinalizedBallot($session->stock->serial->value, $pdfBytes, $payload, $scans, hash('sha256', $pdfBytes));
        $this->store->commitPdf($final->serial, $final->pdfBytes);
        $this->store->commitManifest($final->serial, new EvidenceManifest($final->evidence()));
        return $final;
    }
}
