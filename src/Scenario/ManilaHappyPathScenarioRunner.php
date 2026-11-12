<?php

namespace LBHurtado\BallotPdf\Scenario;

use LBHurtado\BallotPdf\Application\BuildElectionReturn;
use LBHurtado\BallotPdf\Application\BuildReports;
use LBHurtado\BallotPdf\Application\FinalizeBallot;
use LBHurtado\BallotPdf\Application\GenerateStockManifest;
use LBHurtado\BallotPdf\Application\VerifyDirectory;
use LBHurtado\BallotPdf\Contracts\BallotPayloadCodec;
use LBHurtado\BallotPdf\Contracts\PdfMaterializer;
use LBHurtado\BallotPdf\Contracts\WormStore;
use LBHurtado\BallotPdf\Domain\Ballot\BallotSession;
use LBHurtado\BallotPdf\Domain\Context\ContestDefinition;
use LBHurtado\BallotPdf\Domain\Context\ElectionContext;
use LBHurtado\BallotPdf\Domain\Stock\BallotSerial;
use LBHurtado\BallotPdf\Domain\Stock\BallotStock;
use LBHurtado\BallotPdf\Support\CanonicalJson;

final readonly class ManilaHappyPathScenarioRunner
{
    public function __construct(
        private BallotPayloadCodec $codec,
        private PdfMaterializer $materializer,
        private WormStore $store,
    ) {}

    /**
     * Run a deterministic, synthetic Manila composite-ballot ceremony.
     *
     * The package signer is deliberately injected so production can use the
     * commissioned trust roster while tests can use an isolated Ed25519 key.
     *
     * @param array<string, mixed> $obtManifest
     * @param array<string, mixed> $precinctPackage
     * @param array<string, ContestDefinition> $contests
     * @param callable(string, array<string, mixed>): array{key_id: string, signature: string} $packageSigner
     * @return array<string, mixed>
     */
    public function run(
        array $obtManifest,
        array $precinctPackage,
        array $contests,
        int $ballotCount,
        string $serialPrefix,
        string $seed,
        string $root,
        callable $packageSigner,
    ): array {
        if ($ballotCount < 1) {
            throw new \InvalidArgumentException('Happy-path scenario requires at least one ballot.');
        }
        if (trim($serialPrefix) === '' || trim($seed) === '') {
            throw new \InvalidArgumentException('Serial prefix and randomization seed must not be empty.');
        }

        $obtHash = (string) ($obtManifest['source_obt']['sha256'] ?? $obtManifest['obt_hash'] ?? '');
        if (! preg_match('/^[a-f0-9]{64}$/', $obtHash)) {
            throw new \InvalidArgumentException('OBT manifest requires a SHA-256 hash.');
        }

        $precinctId = (string) ($precinctPackage['precinct_id'] ?? '');
        $ballotStyleId = (string) ($precinctPackage['ballot_style_id'] ?? '');
        if ($precinctId === '' || $ballotStyleId === '') {
            throw new \InvalidArgumentException('Precinct package requires precinct_id and ballot_style_id.');
        }
        if (($precinctPackage['obt_hash'] ?? null) !== $obtHash) {
            throw new \RuntimeException('Precinct package does not match the imported OBT hash.');
        }
        if (($precinctPackage['ballot_style_id'] ?? null) !== ($obtManifest['ballot_style_id'] ?? $ballotStyleId)) {
            throw new \RuntimeException('Precinct package does not match the imported OBT ballot style.');
        }
        $serialRange = $precinctPackage['authorized_serial_range'] ?? null;
        if (! is_array($serialRange)
            || ($serialRange['prefix'] ?? null) !== $serialPrefix
            || (int) ($serialRange['start'] ?? 0) > 1
            || (int) ($serialRange['end'] ?? 0) < $ballotCount) {
            throw new \RuntimeException('Requested serials are outside the authorized precinct range.');
        }

        $electionId = (string) ($precinctPackage['election_id'] ?? $obtManifest['election_id'] ?? '');
        $mappingHash = (string) ($obtManifest['mapping_hash'] ?? hash('sha256', CanonicalJson::encode(array_map(
            static fn (ContestDefinition $contest): array => [
                'contest_id' => $contest->contestId,
                'maximum_selections' => $contest->maximumSelections,
                'candidate_codes' => $contest->candidateCodes,
            ],
            $contests,
        ))));
        $context = new ElectionContext($electionId, $precinctId, $ballotStyleId, $mappingHash);

        $this->writeSignedArtifact($root.'/ceremony/obt-import.json', [
            'artifact_type' => 'obt-import',
            'election_id' => $electionId,
            'ballot_style_id' => $ballotStyleId,
            'obt_hash' => $obtHash,
            'device_id' => $precinctPackage['device_id'] ?? null,
            'mode' => 'offline',
        ], $packageSigner);
        $this->writeSignedArtifact($root.'/ceremony/precinct-package.json', $precinctPackage, $packageSigner);

        $stocks = [];
        for ($index = 1; $index <= $ballotCount; $index++) {
            $serial = sprintf('%s-%06d', $serialPrefix, $index);
            $stocks[] = new BallotStock(
                new BallotSerial($serial),
                $context,
                'obt-manila-first-district-2025',
                $obtHash,
                'manila-first-district-2025-assets',
                hash('sha256', CanonicalJson::encode($obtManifest)),
            );
        }

        $stockManifest = (new GenerateStockManifest)->execute($context, $stocks);
        $this->writeJson($root.'/stock/stock-manifest.json', [
            ...$stockManifest->toArray(),
            'manifest_sha256' => $stockManifest->hash(),
            'status' => 'imported-and-available',
        ]);
        $this->writeJson($root.'/ceremony/acm-initialization.json', [
            'artifact_type' => 'acm-initialization',
            'mode' => 'offline',
            'election_context' => $context->toArray(),
            'device_id' => $precinctPackage['device_id'] ?? null,
            'obt_hash' => $obtHash,
            'stock_manifest_sha256' => $stockManifest->hash(),
            'status' => 'verified',
        ]);

        $finalized = [];
        foreach ($stocks as $index => $stock) {
            $session = new BallotSession(
                sprintf('manila-happy-path-%06d', $index + 1),
                $stock,
                $contests,
                'offline-acm-control-number-demo',
            );
            foreach ($contests as $contestId => $contest) {
                $session->select($contestId, $this->randomSelection($contest, $seed, $index + 1));
            }
            $finalized[] = (new FinalizeBallot($this->codec, $this->materializer, $this->store))->execute($session);
        }

        $verification = (new VerifyDirectory($this->codec))->execute($root, $context);
        $electionReturn = (new BuildElectionReturn($this->codec))->execute($verification, $context);
        $this->writeJson($root.'/results/verification-report.json', [
            'scenario_id' => 'manila-1st-district-composite-happy-path',
            'results' => array_map(static fn ($result): array => $result->toArray(), $verification),
        ]);
        $this->writeJson($root.'/results/election-return.json', $electionReturn['artifact']);

        $summary = [
            'scenario_id' => 'manila-1st-district-composite-happy-path',
            'mode' => 'happy-path',
            'deterministic_seed' => $seed,
            'ballot_count' => $ballotCount,
            'obt_hash' => $obtHash,
            'stock_manifest_sha256' => $stockManifest->hash(),
            'finalized_ballots' => array_map(static fn ($ballot): array => [
                'serial' => $ballot->serial,
                'pdf_sha256' => $ballot->pdfSha256,
            ], $finalized),
            'accepted_ballot_count' => count(array_filter($verification, static fn ($result): bool => $result->eligible)),
            'election_return_sha256' => $electionReturn['sha256'],
            'report_json' => (new BuildReports)->json($verification),
            'report_html' => (new BuildReports)->html($verification),
        ];
        $this->writeJson($root.'/results/scenario-summary.json', $summary);

        return [
            ...$summary,
            'verification' => array_map(static fn ($result): array => $result->toArray(), $verification),
            'election_return' => $electionReturn['artifact'],
        ];
    }

    /** @return list<string> */
    private function randomSelection(ContestDefinition $contest, string $seed, int $ballotNumber): array
    {
        $candidates = $contest->candidateCodes;
        usort($candidates, static function (string $left, string $right) use ($seed, $ballotNumber, $contest): int {
            return hash('sha256', "{$seed}|{$ballotNumber}|{$contest->contestId}|{$left}")
                <=> hash('sha256', "{$seed}|{$ballotNumber}|{$contest->contestId}|{$right}");
        });
        return array_slice($candidates, 0, $contest->maximumSelections);
    }

    /** @param array<string, mixed> $artifact */
    private function writeSignedArtifact(string $path, array $artifact, callable $packageSigner): void
    {
        $signature = $packageSigner(CanonicalJson::encode($artifact), $artifact);
        $this->writeJson($path, [
            ...$artifact,
            'signature' => $signature,
        ]);
    }

    /** @param array<string, mixed> $data */
    private function writeJson(string $path, array $data): void
    {
        $directory = dirname($path);
        if (! is_dir($directory) && ! mkdir($directory, 0770, true) && ! is_dir($directory)) {
            throw new \RuntimeException("Unable to create {$directory}.");
        }
        if (file_put_contents($path, CanonicalJson::encode($data)."\n", LOCK_EX) === false) {
            throw new \RuntimeException("Unable to write {$path}.");
        }
    }
}
