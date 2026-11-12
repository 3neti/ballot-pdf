<?php

namespace LBHurtado\BallotPdf\Application;

use LBHurtado\BallotPdf\Contracts\BallotPayloadCodec;
use LBHurtado\BallotPdf\Contracts\PdfMaterializer;
use LBHurtado\BallotPdf\Contracts\PdfSignatureProvider;
use LBHurtado\BallotPdf\Contracts\RecordSigner;
use LBHurtado\BallotPdf\Contracts\AcceptanceLedger;
use LBHurtado\BallotPdf\Contracts\WormStore;
use LBHurtado\BallotPdf\Domain\Ballot\BallotSession;
use LBHurtado\BallotPdf\Domain\Ballot\FinalizedBallot;

final readonly class FinalizeAndAcceptBallot
{
    public function __construct(
        private BallotPayloadCodec $codec,
        private PdfMaterializer $materializer,
        private PdfSignatureProvider $pdfSigner,
        private RecordSigner $manifestSigner,
        private AcceptanceLedger $ledger,
        private WormStore $store,
    ) {}

    /** @return array{finalized: FinalizedBallot, acceptance: \LBHurtado\BallotPdf\Domain\Evidence\AcceptanceRecord} */
    public function execute(BallotSession $session, string $idempotencyKey, string $acceptedAt): array
    {
        $session->validate();
        $payload = [
            ...$session->stock->toPayload(),
            'candidate_codes' => array_values(array_merge(...array_values($session->selections()) ?: [[]])),
            'selections' => $session->selections(),
        ];
        $scans = $this->codec->encode($payload);
        $pdfBytes = $this->materializer->materialize($payload, $scans);
        $finalized = new FinalizedBallot(
            $session->stock->serial->value,
            $pdfBytes,
            $payload,
            $scans,
            hash('sha256', $pdfBytes),
        );

        $acceptanceBundle = (new AcceptFinalizedBallot(
            $this->pdfSigner,
            $this->manifestSigner,
            $this->ledger,
            $this->store,
        ))->executeBundle($finalized, $idempotencyKey, $acceptedAt);
        $acceptance = $acceptanceBundle['record'];
        $finalized = new FinalizedBallot(
            $finalized->serial,
            $acceptanceBundle['signed_pdf_bytes'],
            $finalized->payload,
            $finalized->truthQrScans,
            $acceptance->pdfSha256,
        );

        return ['finalized' => $finalized, 'acceptance' => $acceptance, 'manifest' => $acceptanceBundle['manifest']];
    }
}
