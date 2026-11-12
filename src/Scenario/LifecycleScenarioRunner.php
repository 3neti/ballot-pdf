<?php

namespace LBHurtado\BallotPdf\Scenario;

use LBHurtado\BallotPdf\Application\BuildReports;
use LBHurtado\BallotPdf\Application\BuildElectionReturn;
use LBHurtado\BallotPdf\Application\CloseElection;
use LBHurtado\BallotPdf\Application\FinalizeAndAcceptBallot;
use LBHurtado\BallotPdf\Application\RecordAuditOutput;
use LBHurtado\BallotPdf\Application\VerifyDirectory;
use LBHurtado\BallotPdf\Contracts\BallotPayloadCodec;
use LBHurtado\BallotPdf\Contracts\PdfMaterializer;
use LBHurtado\BallotPdf\Contracts\WormStore;
use LBHurtado\BallotPdf\Domain\Ballot\BallotSession;
use LBHurtado\BallotPdf\Domain\Context\ContestDefinition;
use LBHurtado\BallotPdf\Domain\Context\ElectionContext;
use LBHurtado\BallotPdf\Domain\Stock\BallotStock;
use LBHurtado\BallotPdf\Infrastructure\Signing\DeterministicPdfSignatureProvider;
use LBHurtado\BallotPdf\Infrastructure\Signing\DeterministicRecordSigner;
use LBHurtado\BallotPdf\Infrastructure\Signing\DeterministicRecordVerifier;
use LBHurtado\BallotPdf\Infrastructure\Storage\FilesystemAcceptanceLedger;
use LBHurtado\BallotPdf\Infrastructure\Audit\ReferenceAuditOutputRenderer;
use LBHurtado\BallotPdf\Infrastructure\Audit\ReferenceAuditReadback;
use LBHurtado\BallotPdf\Infrastructure\Audit\ReferencePrintAdapter;

final readonly class LifecycleScenarioRunner
{
    public function __construct(
        private BallotPayloadCodec $codec,
        private PdfMaterializer $materializer,
        private WormStore $store,
    ) {}

    /**
     * Runs the deterministic ballot-PDF lifecycle used by the prototype.
     *
     * @param array<string, ContestDefinition> $contests
     * @param array<string, list<string>> $selections
     * @return array<string, mixed>
     */
    public function run(
        ElectionContext $context,
        BallotStock $stock,
        array $contests,
        array $selections,
        string $root,
    ): array {
        $session = new BallotSession('scenario-session-1', $stock, $contests, 'scenario-authorization');
        $overSelectionRejected = false;
        try {
            $contestId = array_key_first($selections);
            if ($contestId !== null) {
                $limit = $contests[$contestId]->maximumSelections;
                $candidates = $contests[$contestId]->candidateCodes;
                $session->select($contestId, array_slice($candidates, 0, $limit + 1));
            }
        } catch (\DomainException) {
            $overSelectionRejected = true;
        }

        foreach ($selections as $contestId => $candidateCodes) {
            $session->select($contestId, $candidateCodes);
        }

        $ledger = new FilesystemAcceptanceLedger($root.'/ledger');
        $recordSigner = new DeterministicRecordSigner;
        $accepted = (new FinalizeAndAcceptBallot(
            $this->codec,
            $this->materializer,
            new DeterministicPdfSignatureProvider,
            $recordSigner,
            $ledger,
            $this->store,
        ))->execute($session, 'scenario-acceptance-1', '2026-11-13T12:00:00+08:00');
        $finalized = $accepted['finalized'];
        $verification = (new VerifyDirectory($this->codec, null, $ledger, new DeterministicRecordVerifier, new DeterministicPdfSignatureProvider))->execute($root, $context);
        $close = (new CloseElection($ledger, $this->store, $recordSigner))->execute(
            $context->electionId,
            $context->precinctId,
            '2026-11-13T12:01:00+08:00',
            hash('sha256', 'demo-tally-rules'),
            hash('sha256', 'demo-trust-roster'),
            ['accepted' => count($ledger->records())],
        );
        $audit = (new RecordAuditOutput(
            new ReferenceAuditOutputRenderer,
            new ReferencePrintAdapter,
            new ReferenceAuditReadback($finalized->truthQrScans),
            $this->codec,
        ))->execute($finalized, $accepted['acceptance'], 'accepted', [['actor' => 'BEI', 'action' => 'sealed']]);
        $electionReturn = (new BuildElectionReturn($this->codec))->execute($verification, $context);
        $decoded = $this->codec->decode($finalized->truthQrScans);
        $tally = [];
        foreach ((array) ($decoded['candidate_codes'] ?? []) as $candidateCode) {
            $tally[$candidateCode] = ($tally[$candidateCode] ?? 0) + 1;
        }
        ksort($tally);

        $repeatFinalizationIdempotent = false;
        try {
            $retry = (new FinalizeAndAcceptBallot(
                $this->codec,
                $this->materializer,
                new DeterministicPdfSignatureProvider,
                $recordSigner,
                $ledger,
                $this->store,
            ))->execute($session, 'scenario-acceptance-1', '2026-11-13T12:00:00+08:00');
            $repeatFinalizationIdempotent = $retry['acceptance']->acceptanceId === $accepted['acceptance']->acceptanceId;
        } catch (\RuntimeException) {
            $repeatFinalizationIdempotent = false;
        }

        return [
            'scenario' => 'ballot-pdf-lifecycle',
            'stock' => 'available',
            'session' => 'authorized',
            'selection' => $session->selections(),
            'over_selection_rejected' => $overSelectionRejected,
            'finalization' => 'committed',
            'serial' => $finalized->serial,
            'pdf_sha256' => $finalized->pdfSha256,
            'truth_qr_verified' => ($decoded['truth_signature_valid'] ?? false) === true,
            'verification' => array_map(static fn ($result): array => $result->toArray(), $verification),
            'tally' => $tally,
            'report_json' => (new BuildReports)->json($verification),
            'report_html' => (new BuildReports)->html($verification),
            'election_return' => $electionReturn['artifact'],
            'election_return_sha256' => $electionReturn['sha256'],
            'acceptance' => $accepted['acceptance']->toArray(),
            'election_close_certificate' => $close->toArray(),
            'election_close_certificate_sha256' => $close->hash(),
            'audit_record' => $audit['record']->toArray(),
            'audit_record_sha256' => $audit['record']->hash(),
            'repeat_finalization_idempotent' => $repeatFinalizationIdempotent,
        ];
    }
}
