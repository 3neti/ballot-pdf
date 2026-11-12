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

    public function execute(FinalizedBallot $final, string $idempotencyKey, string $acceptedAt, ?int $sequence = null): AcceptanceRecord
    {
        return $this->executeBundle($final, $idempotencyKey, $acceptedAt, $sequence)['record'];
    }

    /** @return array{record: AcceptanceRecord, signed_pdf_bytes: string, manifest: EvidenceManifest} */
    public function executeBundle(FinalizedBallot $final, string $idempotencyKey, string $acceptedAt, ?int $sequence = null): array
    {
        $fingerprint = $this->fingerprint($final, $idempotencyKey);
        $existing = $this->ledger->byIdempotency($idempotencyKey);
        if ($existing !== null) {
            if ($existing->contentFingerprint !== '' && $existing->contentFingerprint !== $fingerprint) {
                throw new \RuntimeException('Acceptance idempotency conflict.');
            }
            return [
                'record' => $existing,
                'signed_pdf_bytes' => $this->store->pdf($existing->serial) ?? $final->pdfBytes,
                'manifest' => $this->store->manifest($existing->serial) ?? new EvidenceManifest([
                    'ballot_serial' => $existing->serial,
                    'pdf_sha256' => $existing->pdfSha256,
                ]),
            ];
        }

        if ($this->ledger->isClosed()) {
            throw new \RuntimeException('Acceptance ledger is closed.');
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
        $acceptanceSequence = $sequence ?? $this->ledger->allocateSequence();
        $unsignedManifest = [
            'schema' => 'ballot-pdf.acceptance-manifest.v1',
            'ballot_serial' => $final->serial,
            'pdf_sha256' => $pdfHash,
            'payload_sha256' => $payloadHash,
            'truth_qr_payload_sha256' => $payloadHash,
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
            'acceptance_id' => 'acceptance-'.$idempotencyKey,
            'content_fingerprint' => $fingerprint,
            'acceptance_sequence' => $acceptanceSequence,
            'artifact_refs' => [
                'pdf' => 'pdf/'.$final->serial.'.pdf',
                'manifest' => 'manifest/'.$final->serial.'.json',
                'ledger' => 'ledger/acceptance-'.$idempotencyKey.'.json',
            ],
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
        (new ValidateAcceptanceManifest)->execute($manifest->toArray());

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
            $acceptanceSequence,
            $unsignedManifest['context'],
            'accepted',
            $fingerprint,
        );
        $this->ledger->accept($record);

        return ['record' => $record, 'signed_pdf_bytes' => $signedPdf, 'manifest' => $manifest];
    }

    private function fingerprint(FinalizedBallot $final, string $idempotencyKey): string
    {
        return hash('sha256', CanonicalJson::encode([
            'ballot_serial' => $final->serial,
            'payload_sha256' => hash('sha256', CanonicalJson::encode($final->payload)),
            'source_pdf_sha256' => hash('sha256', $final->pdfBytes),
            'context' => [
                'election_id' => $final->payload['election_id'] ?? null,
                'precinct_id' => $final->payload['precinct_id'] ?? null,
                'ballot_style_id' => $final->payload['ballot_style_id'] ?? null,
                'device_id' => $final->payload['device_id'] ?? null,
                'mapping_hash' => $final->payload['mapping_hash'] ?? null,
                'obt_hash' => $final->payload['obt_hash'] ?? null,
            ],
            'idempotency_key' => $idempotencyKey,
        ]));
    }
}
