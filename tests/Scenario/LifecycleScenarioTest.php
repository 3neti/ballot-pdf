<?php

use LBHurtado\BallotPdf\Contracts\BallotPayloadCodec;
use LBHurtado\BallotPdf\Contracts\PdfMaterializer;
use LBHurtado\BallotPdf\Domain\Context\ContestDefinition;
use LBHurtado\BallotPdf\Domain\Context\ElectionContext;
use LBHurtado\BallotPdf\Domain\Stock\BallotSerial;
use LBHurtado\BallotPdf\Domain\Stock\BallotStock;
use LBHurtado\BallotPdf\Infrastructure\Storage\FilesystemWormStore;
use LBHurtado\BallotPdf\Scenario\LifecycleScenarioRunner;

it('runs the complete deterministic ballot PDF lifecycle', function (): void {
    $codec = new class implements BallotPayloadCodec {
        public function encode(array $payload): array
        {
            return ['truth://scenario/'.base64_encode(json_encode($payload, JSON_THROW_ON_ERROR))];
        }

        public function decode(array $scans): array
        {
            return [...json_decode(base64_decode(substr($scans[0], 17)), true, 512, JSON_THROW_ON_ERROR), 'truth_signature_valid' => true];
        }
    };
    $materializer = new class implements PdfMaterializer {
        public function materialize(array $payload, array $truthQrScans): string
        {
            return '%PDF-scenario-'.hash('sha256', $truthQrScans[0]);
        }
    };
    $root = sys_get_temp_dir().'/ballot-pdf-scenario-'.bin2hex(random_bytes(4));
    $context = new ElectionContext('demo-election', 'precinct-1', 'style-1', 'mapping-1');
    $stock = new BallotStock(new BallotSerial('PRECINCT-1-000001'), $context, 'obt-1', 'obt-hash', 'assets-1', 'assets-hash');

    $result = (new LifecycleScenarioRunner($codec, $materializer, new FilesystemWormStore($root)))->run(
        $context,
        $stock,
        ['senator' => new ContestDefinition('senator', 2, ['C1', 'C2', 'C3'])],
        ['senator' => ['C1', 'C2']],
        $root,
    );

    expect($result['over_selection_rejected'])->toBeTrue()
        ->and($result['finalization'])->toBe('committed')
        ->and($result['truth_qr_verified'])->toBeTrue()
        ->and($result['tally'])->toBe(['C1' => 1, 'C2' => 1])
        ->and($result['election_return']['artifact_type'])->toBe('election-return')
        ->and($result['election_return']['eligible_ballot_count'])->toBe(1)
        ->and($result['election_return']['tally'])->toBe(['C1' => 1, 'C2' => 1])
        ->and($result['election_return_sha256'])->toHaveLength(64)
        ->and($result['verification'][0]['classification'])->toBe('accepted')
        ->and($result['report_json'])->toContain('accepted_count')
        ->and($result['report_html'])->toContain('<table>')
        ->and($result['repeat_finalization_rejected'])->toBeTrue();
});
