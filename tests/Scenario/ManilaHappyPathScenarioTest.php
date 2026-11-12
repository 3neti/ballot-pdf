<?php

use LBHurtado\BallotPdf\Contracts\BallotPayloadCodec;
use LBHurtado\BallotPdf\Contracts\PdfMaterializer;
use LBHurtado\BallotPdf\Domain\Context\ContestDefinition;
use LBHurtado\BallotPdf\Infrastructure\Storage\FilesystemWormStore;
use LBHurtado\BallotPdf\Scenario\ManilaHappyPathScenarioRunner;

it('runs the Manila composite OBT happy path from offline import to election return', function (): void {
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
            return '%PDF-manila-'.hash('sha256', json_encode([$payload, $truthQrScans], JSON_THROW_ON_ERROR));
        }
    };
    $root = sys_get_temp_dir().'/ballot-pdf-manila-happy-'.bin2hex(random_bytes(4));
    $obtHash = str_repeat('a', 64);
    $contests = [
        'senator' => new ContestDefinition('senator', 12, array_map(static fn (int $number): string => sprintf('SEN-%03d', $number), range(1, 66))),
        'party_list' => new ContestDefinition('party_list', 1, ['PARTY-001', 'PARTY-002']),
        'house' => new ContestDefinition('house', 1, ['HOUSE-001', 'HOUSE-002']),
        'mayor' => new ContestDefinition('mayor', 1, ['MAYOR-001', 'MAYOR-002']),
        'vice_mayor' => new ContestDefinition('vice_mayor', 1, ['VICE-001', 'VICE-002']),
        'councilor' => new ContestDefinition('councilor', 6, ['COUNCIL-001', 'COUNCIL-002', 'COUNCIL-003', 'COUNCIL-004', 'COUNCIL-005', 'COUNCIL-006']),
    ];
    $precinctPackage = [
        'schema_version' => 'precinct-package-1',
        'election_id' => '2025NLE',
        'precinct_id' => '39010001',
        'clustered_precinct_id' => '39010001',
        'polling_place' => 'Barangay 1, Tondo, Manila',
        'ballot_style_id' => 'MANILA-1ST-DISTRICT-COMPOSITE-2025',
        'device_id' => 'ACM-MANILA-001',
        'obt_hash' => $obtHash,
        'authorized_serial_range' => ['prefix' => '39010001-SIM', 'start' => 1, 'end' => 3],
    ];

    $result = (new ManilaHappyPathScenarioRunner($codec, $materializer, new FilesystemWormStore($root)))->run(
        [
            'schema_version' => 'automation-ready-obt-1',
            'election_id' => '2025NLE',
            'ballot_style_id' => 'MANILA-1ST-DISTRICT-COMPOSITE-2025',
            'source_obt' => ['sha256' => $obtHash],
        ],
        $precinctPackage,
        $contests,
        3,
        '39010001-SIM',
        'manila-happy-path-seed',
        $root,
        static fn (string $bytes, array $artifact): array => [
            'key_id' => 'demo-ceremony-key',
            'signature' => base64_encode(hash('sha256', $bytes, true)),
        ],
    );

    expect($result['mode'])->toBe('happy-path')
        ->and($result['ballot_count'])->toBe(3)
        ->and($result['accepted_ballot_count'])->toBe(3)
        ->and($result['finalized_ballots'])->toHaveCount(3)
        ->and($result['election_return']['eligible_ballot_count'])->toBe(3)
        ->and($result['election_return_sha256'])->toHaveLength(64)
        ->and($result['verification'])->each->toHaveKey('classification', 'accepted')
        ->and($result['verification'])->each->toHaveKey('eligible', true)
        ->and(is_file($root.'/ceremony/obt-import.json'))->toBeTrue()
        ->and(is_file($root.'/ceremony/acm-initialization.json'))->toBeTrue()
        ->and(is_file($root.'/stock/stock-manifest.json'))->toBeTrue()
        ->and(is_file($root.'/results/election-return.json'))->toBeTrue()
        ->and(count(iterator_to_array((new FilesystemWormStore($root))->pdfs())))->toBe(3);
});
