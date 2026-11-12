<?php

use LBHurtado\BallotPdf\Application\CloseElection;
use LBHurtado\BallotPdf\Application\FinalizeAndAcceptBallot;
use LBHurtado\BallotPdf\Application\RecordAuditOutput;
use LBHurtado\BallotPdf\Contracts\BallotPayloadCodec;
use LBHurtado\BallotPdf\Contracts\PdfMaterializer;
use LBHurtado\BallotPdf\Domain\Ballot\BallotSession;
use LBHurtado\BallotPdf\Domain\Context\ContestDefinition;
use LBHurtado\BallotPdf\Domain\Context\ElectionContext;
use LBHurtado\BallotPdf\Domain\Stock\BallotSerial;
use LBHurtado\BallotPdf\Domain\Stock\BallotStock;
use LBHurtado\BallotPdf\Infrastructure\Audit\ReferenceAuditOutputRenderer;
use LBHurtado\BallotPdf\Infrastructure\Audit\ReferenceAuditReadback;
use LBHurtado\BallotPdf\Infrastructure\Audit\ReferencePrintAdapter;
use LBHurtado\BallotPdf\Infrastructure\Signing\DeterministicPdfSignatureProvider;
use LBHurtado\BallotPdf\Infrastructure\Signing\DeterministicRecordSigner;
use LBHurtado\BallotPdf\Infrastructure\Storage\FilesystemAcceptanceLedger;
use LBHurtado\BallotPdf\Infrastructure\Storage\FilesystemWormStore;

function authenticatedWorkflowFixture(): array
{
    $codec = new class implements BallotPayloadCodec {
        public function encode(array $payload): array { return ['demo://'.base64_encode(json_encode($payload, JSON_THROW_ON_ERROR))]; }
        public function decode(array $scans): array { return json_decode(base64_decode(substr($scans[0], 7)), true, 512, JSON_THROW_ON_ERROR); }
    };
    $materializer = new class implements PdfMaterializer {
        public function materialize(array $payload, array $truthQrScans): string { return '%PDF-'.hash('sha256', $truthQrScans[0]); }
    };
    $context = new ElectionContext('demo-election', 'precinct-1', 'style-1', 'mapping-1');
    $stock = new BallotStock(new BallotSerial('SERIAL-1'), $context, 'obt-1', 'obt-hash', 'assets-1', 'assets-hash');
    $session = new BallotSession('session-1', $stock, ['senator' => new ContestDefinition('senator', 2, ['C1', 'C2', 'C3'])], 'auth-1');
    $session->select('senator', ['C1', 'C2']);
    return [$codec, $materializer, $context, $session];
}

it('commits the canonical bundle and makes identical retries idempotent', function (): void {
    [$codec, $materializer, $context, $session] = authenticatedWorkflowFixture();
    $root = sys_get_temp_dir().'/ballot-pdf-authenticated-'.bin2hex(random_bytes(4));
    $ledger = new FilesystemAcceptanceLedger($root.'/ledger');
    $store = new FilesystemWormStore($root.'/worm');
    $signer = new DeterministicRecordSigner;
    $service = new FinalizeAndAcceptBallot($codec, $materializer, new DeterministicPdfSignatureProvider, $signer, $ledger, $store);

    $first = $service->execute($session, 'request-1', '2026-11-13T12:00:00+08:00');
    $retry = $service->execute($session, 'request-1', '2026-11-13T12:00:00+08:00');

    expect($retry['acceptance']->acceptanceId)->toBe($first['acceptance']->acceptanceId)
        ->and($retry['acceptance']->contentFingerprint)->toHaveLength(64)
        ->and($ledger->recoveryReport()['sequence_consistent'])->toBeTrue();
});

it('freezes acceptance at signed closure and records a verified A4 output', function (): void {
    [$codec, $materializer, $context, $session] = authenticatedWorkflowFixture();
    $root = sys_get_temp_dir().'/ballot-pdf-close-'.bin2hex(random_bytes(4));
    $ledger = new FilesystemAcceptanceLedger($root.'/ledger');
    $store = new FilesystemWormStore($root.'/worm');
    $signer = new DeterministicRecordSigner;
    $accepted = (new FinalizeAndAcceptBallot($codec, $materializer, new DeterministicPdfSignatureProvider, $signer, $ledger, $store))
        ->execute($session, 'request-1', '2026-11-13T12:00:00+08:00');
    $certificate = (new CloseElection($ledger, $store, $signer))->execute('demo-election', 'precinct-1', '2026-11-13T12:01:00+08:00', hash('sha256', 'rules'), hash('sha256', 'roster'));
    $audit = (new RecordAuditOutput(
        new ReferenceAuditOutputRenderer,
        new ReferencePrintAdapter,
        new ReferenceAuditReadback($accepted['finalized']->truthQrScans),
        $codec,
    ))->execute($accepted['finalized'], $accepted['acceptance'], 'accepted', [['actor' => 'BEI', 'action' => 'sealed']]);

    expect($certificate->signature['key_id'])->toBe('reference-record-key')
        ->and(fn () => $ledger->allocateSequence())->toThrow(RuntimeException::class, 'closed')
        ->and($audit['record']->disposition)->toBe('verified')
        ->and($audit['record']->hash())->toHaveLength(64)
        ->and(is_file($root.'/worm/closure/election-close.json'))->toBeTrue();
});
