<?php

namespace LBHurtado\BallotPdf\Application;

use LBHurtado\BallotPdf\Contracts\AcceptanceLedger;
use LBHurtado\BallotPdf\Contracts\PdfSignatureProvider;
use LBHurtado\BallotPdf\Contracts\RecordSigner;
use LBHurtado\BallotPdf\Contracts\WormStore;
use LBHurtado\BallotPdf\Domain\Ballot\FinalizedBallot;
use LBHurtado\BallotPdf\Domain\Evidence\AcceptanceRecord;
use LBHurtado\BallotPdf\Domain\Evidence\EvidenceManifest;
use LBHurtado\BallotPdf\Support\CanonicalJson;

final readonly class AcceptFinalizedBallot
{
    public function __construct(
        private PdfSignatureProvider $pdfSigner,
        private RecordSigner $manifestSigner,
        private AcceptanceLedger $ledger,
        private WormStore $store,
    ) {}

    public function execute(FinalizedBallot $final, string $idempotencyKey, string $acceptedAt, int $sequence): AcceptanceRecord
    {
        $existing = $this->ledger->byIdempotency($idempotencyKey);
        if ($existing !== null) {
            return $existing;
        }

        $electionId = (string) ($final->payload['election_id'] ?? '');
        $precinctId = (string) ($final->payload['precinct_id'] ?? '');
        if ($electionId === '' || $precinctId === '') {
            throw new \InvalidArgumentException('Finalized ballot context is incomplete.');
        }

        $pdfSignature = $this->pdfSigner->sign($final->pdfBytes, $electionId, $precinctId);
        $signedPdf = (string) ($pdfSignature['pdf_bytes'] ?? '');
        if ($signedPdf === '') {
            throw new \RuntimeException('PDF signature provider returned empty bytes.');
        }

        $pdfHash = hash('sha256', $signedPdf);
        $payloadHash = hash('sha256', CanonicalJson::encode($final->payload));
        $unsignedManifest = [
            'schema' => 'ballot-pdf.acceptance-manifest.v1',
            'ballot_serial' => $final->serial,
            'pdf_sha256' => $pdfHash,
            'payload_sha256' => $payloadHash,
            'truth_qr_scans' => $final->truthQrScans,
            'pdf_signature' => [
                'algorithm' => (string) ($pdfSignature['algorithm'] ?? ''),
                'key_id' => (string) ($pdfSignature['key_id'] ?? ''),
                'signature' => (string) ($pdfSignature['signature'] ?? ''),
            ],
            'context' => [
                'election_id' => $electionId,
                'precinct_id' => $precinctId,
                'ballot_style_id' => (string) ($final->payload['ballot_style_id'] ?? ''),
                'device_id' => (string) ($final->payload['device_id'] ?? ''),
            ],
            'idempotency_key' => $idempotencyKey,
            'status' => 'accepted',
        ];
        $manifestSignature = $this->manifestSigner->sign(
            'ballot.acceptance-manifest',
            $electionId,
            $precinctId,
            CanonicalJson::encode($unsignedManifest),
        );
        $manifest = new EvidenceManifest([
            ...$unsignedManifest,
            'manifest_signature' => $manifestSignature,
        ]);

        // Store first. If the ledger commit is interrupted, the artifact remains
        // verifiable evidence but is excluded because no acceptance record exists.
        $this->store->commitPdf($final->serial, $signedPdf);
        $this->store->commitManifest($final->serial, $manifest);

        $record = new AcceptanceRecord(
            'acceptance-'.$idempotencyKey,
            $idempotencyKey,
            $final->serial,
            $pdfHash,
            $payloadHash,
            $manifest->hash(),
            $acceptedAt,
            $sequence,
            $unsignedManifest['context'],
        );
        $this->ledger->accept($record);

        return $record;
    }
}
