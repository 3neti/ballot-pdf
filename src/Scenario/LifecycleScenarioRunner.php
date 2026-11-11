<?php

namespace LBHurtado\BallotPdf\Scenario;

use LBHurtado\BallotPdf\Application\BuildReports;
use LBHurtado\BallotPdf\Application\BuildElectionReturn;
use LBHurtado\BallotPdf\Application\FinalizeBallot;
use LBHurtado\BallotPdf\Application\VerifyDirectory;
use LBHurtado\BallotPdf\Contracts\BallotPayloadCodec;
use LBHurtado\BallotPdf\Contracts\PdfMaterializer;
use LBHurtado\BallotPdf\Contracts\WormStore;
use LBHurtado\BallotPdf\Domain\Ballot\BallotSession;
use LBHurtado\BallotPdf\Domain\Context\ContestDefinition;
use LBHurtado\BallotPdf\Domain\Context\ElectionContext;
use LBHurtado\BallotPdf\Domain\Stock\BallotStock;

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

        $finalized = (new FinalizeBallot($this->codec, $this->materializer, $this->store))->execute($session);
        $verification = (new VerifyDirectory($this->codec))->execute($root, $context);
        $electionReturn = (new BuildElectionReturn($this->codec))->execute($verification, $context);
        $decoded = $this->codec->decode($finalized->truthQrScans);
        $tally = [];
        foreach ((array) ($decoded['candidate_codes'] ?? []) as $candidateCode) {
            $tally[$candidateCode] = ($tally[$candidateCode] ?? 0) + 1;
        }
        ksort($tally);

        $repeatFinalizationRejected = false;
        try {
            (new FinalizeBallot($this->codec, $this->materializer, $this->store))->execute($session);
        } catch (\RuntimeException $exception) {
            $repeatFinalizationRejected = str_contains($exception->getMessage(), 'WORM store refuses overwrite');
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
            'repeat_finalization_rejected' => $repeatFinalizationRejected,
        ];
    }
}
