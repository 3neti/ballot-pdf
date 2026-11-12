<?php

use LBHurtado\BallotPdf\Application\BuildElectionCloseCertificate;
use LBHurtado\BallotPdf\Application\AcceptFinalizedBallot;
use LBHurtado\BallotPdf\Contracts\PdfSignatureProvider;
use LBHurtado\BallotPdf\Contracts\RecordSigner;
use LBHurtado\BallotPdf\Domain\Ballot\FinalizedBallot;
use LBHurtado\BallotPdf\Domain\Evidence\AcceptanceRecord;
use LBHurtado\BallotPdf\Infrastructure\Storage\FilesystemWormStore;
use LBHurtado\BallotPdf\Infrastructure\Storage\FilesystemAcceptanceLedger;

it('accepts idempotent ballot records and rejects changed retries', function (): void {
    $ledger = new FilesystemAcceptanceLedger(sys_get_temp_dir().'/ballot-pdf-acceptance-'.bin2hex(random_bytes(4)));
    $record = new AcceptanceRecord(
        'acceptance-1', 'request-1', 'SERIAL-1', str_repeat('a', 64), str_repeat('b', 64), str_repeat('c', 64),
        '2026-11-12T12:00:00+08:00', 1, ['election_id' => 'demo'],
    );
    $ledger->accept($record);
    $ledger->accept($record);

    expect($ledger->byIdempotency('request-1')?->hash())->toBe($record->hash());
    expect(fn () => $ledger->accept(new AcceptanceRecord(
        'acceptance-2', 'request-1', 'SERIAL-1', str_repeat('d', 64), str_repeat('b', 64), str_repeat('c', 64),
        '2026-11-12T12:00:01+08:00', 2, ['election_id' => 'demo'],
    )))->toThrow(RuntimeException::class, 'Acceptance idempotency conflict');
});

it('builds a deterministic close certificate from the authenticated cast set', function (): void {
    $records = [
        new AcceptanceRecord('a-2', 'r-2', 'SERIAL-2', str_repeat('2', 64), str_repeat('b', 64), str_repeat('c', 64), '2026-11-12T12:00:02+08:00', 2, ['election_id' => 'demo']),
        new AcceptanceRecord('a-1', 'r-1', 'SERIAL-1', str_repeat('1', 64), str_repeat('b', 64), str_repeat('c', 64), '2026-11-12T12:00:01+08:00', 1, ['election_id' => 'demo']),
    ];
    $certificate = (new BuildElectionCloseCertificate)->execute('demo', 'precinct-1', '2026-11-12T12:01:00+08:00', $records, str_repeat('d', 64), str_repeat('e', 64));

    expect($certificate->acceptedSerials)->toBe(['SERIAL-1', 'SERIAL-2'])
        ->and($certificate->lastAcceptanceSequence)->toBe(2)
        ->and($certificate->toArray()['cast_set_commitment'])->toHaveLength(64)
        ->and($certificate->hash())->toHaveLength(64);
});

it('commits the dual-authenticated ballot bundle before ledger acceptance', function (): void {
    $root = sys_get_temp_dir().'/ballot-pdf-accept-'.bin2hex(random_bytes(4));
    $final = new FinalizedBallot(
        'SERIAL-1',
        '%PDF-demo',
        [
            'election_id' => 'demo',
            'precinct_id' => 'precinct-1',
            'ballot_style_id' => 'style-1',
            'device_id' => 'device-1',
            'candidate_codes' => ['C1'],
        ],
        ['truth://signed-payload'],
        hash('sha256', '%PDF-demo'),
    );
    $pdfSigner = new class implements PdfSignatureProvider {
        public function sign(string $pdfBytes, string $electionId, string $precinctId): array
        {
            return ['pdf_bytes' => $pdfBytes."\n%PDF-SIGNATURE", 'algorithm' => 'Ed25519-PDF-adapter', 'key_id' => 'pdf-key-1', 'signature' => 'pdf-signature'];
        }
    };
    $manifestSigner = new class implements RecordSigner {
        public function sign(string $purpose, string $electionId, string $precinctId, string $bytes): array
        {
            return ['key_id' => 'manifest-key-1', 'signature' => hash('sha256', $purpose.$electionId.$precinctId.$bytes)];
        }
    };
    $ledger = new FilesystemAcceptanceLedger($root.'/ledger');
    $record = (new AcceptFinalizedBallot($pdfSigner, $manifestSigner, $ledger, new FilesystemWormStore($root.'/worm')))
        ->execute($final, 'request-1', '2026-11-12T12:00:00+08:00', 1);

    expect($record->status)->toBe('accepted')
        ->and($record->pdfSha256)->toBe(hash('sha256', "%PDF-demo\n%PDF-SIGNATURE"))
        ->and($ledger->byIdempotency('request-1')?->serial)->toBe('SERIAL-1');
});
