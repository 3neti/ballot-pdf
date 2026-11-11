<?php

use LBHurtado\BallotPdf\Application\BuildReports;
use LBHurtado\BallotPdf\Application\AuthorizeSession;
use LBHurtado\BallotPdf\Application\FinalizeBallot;
use LBHurtado\BallotPdf\Application\GenerateStockManifest;
use LBHurtado\BallotPdf\Application\VerifyDirectory;
use LBHurtado\BallotPdf\Contracts\BallotPayloadCodec;
use LBHurtado\BallotPdf\Contracts\PdfMaterializer;
use LBHurtado\BallotPdf\Contracts\WormStore;
use LBHurtado\BallotPdf\Contracts\BallotStockRepository;
use LBHurtado\BallotPdf\Contracts\ControlNumberAuthorizer;
use LBHurtado\BallotPdf\Domain\Ballot\BallotSession;
use LBHurtado\BallotPdf\Domain\Context\ContestDefinition;
use LBHurtado\BallotPdf\Domain\Context\ElectionContext;
use LBHurtado\BallotPdf\Domain\Evidence\EvidenceManifest;
use LBHurtado\BallotPdf\Domain\Stock\BallotSerial;
use LBHurtado\BallotPdf\Domain\Stock\BallotStock;
use LBHurtado\BallotPdf\Infrastructure\Storage\FilesystemWormStore;
use LBHurtado\BallotPdf\Protocol\ReferenceProtocolService;

function testSession(): BallotSession
{
    $context = new ElectionContext('demo-election', 'precinct-1', 'style-1', 'mapping-1');
    $stock = new BallotStock(new BallotSerial('PRECINCT-1-000001'), $context, 'obt-1', 'obt-hash', 'assets-1', 'assets-hash');
    return new BallotSession('session-1', $stock, [
        'senator' => new ContestDefinition('senator', 2, ['C1', 'C2', 'C3']),
    ], 'auth-1');
}

it('enforces contest limits and builds a finalized artifact', function (): void {
    $session = testSession();
    $session->select('senator', ['C1', 'C2']);
    expect(fn () => $session->select('senator', ['C1', 'C2', 'C3']))->toThrow(DomainException::class);

    $codec = new class implements BallotPayloadCodec {
        public function encode(array $payload): array { return ['truth://demo/'.base64_encode(json_encode($payload, JSON_THROW_ON_ERROR))]; }
        public function decode(array $scans): array { return json_decode(base64_decode(substr($scans[0], 14)), true, 512, JSON_THROW_ON_ERROR); }
    };
    $materializer = new class implements PdfMaterializer {
        public function materialize(array $payload, array $truthQrScans): string { return "%PDF-demo\n".json_encode($payload, JSON_THROW_ON_ERROR); }
    };
    $root = sys_get_temp_dir().'/ballot-pdf-'.bin2hex(random_bytes(4));
    $final = (new FinalizeBallot($codec, $materializer, new FilesystemWormStore($root)))->execute($session);

    expect($final->serial)->toBe('PRECINCT-1-000001')
        ->and($final->pdfSha256)->toBe(hash('sha256', $final->pdfBytes))
        ->and(file_exists($root.'/pdf/PRECINCT-1-000001.pdf'))->toBeTrue();
});

it('generates a context-bound stock manifest and authorizes a session', function (): void {
    $session = testSession();
    $manifest = (new GenerateStockManifest)->execute($session->stock->context, [$session->stock]);
    $authorizer = new class implements ControlNumberAuthorizer {
        public function authorize(string $controlNumber): array { return ['reference' => 'auth-ref', 'ballot_serial' => 'PRECINCT-1-000001']; }
    };
    $repository = new class($session->stock) implements BallotStockRepository {
        public function __construct(private readonly BallotStock $stock) {}
        public function reserve(string $serial): BallotStock { return $this->stock; }
    };
    $authorized = (new AuthorizeSession($authorizer, $repository, [
        'senator' => new ContestDefinition('senator', 2, ['C1', 'C2', 'C3']),
    ]))->execute('session-2', 'DEMO-000001');

    expect($manifest->hash())->toHaveLength(64)->and($authorized->stock->serial->value)->toBe('PRECINCT-1-000001');
});

it('refuses to overwrite finalized PDF evidence', function (): void {
    $store = new FilesystemWormStore(sys_get_temp_dir().'/ballot-pdf-worm-'.bin2hex(random_bytes(4)));
    $store->commitPdf('SERIAL-1', 'first');
    expect(fn () => $store->commitPdf('SERIAL-1', 'second'))->toThrow(RuntimeException::class);
});

it('validates protocol envelopes and produces deterministic reports', function (): void {
    $service = new ReferenceProtocolService(
        static fn (array $message): array => ['status' => 'session_opened', 'session_id' => 'session-1'],
        static fn (array $message): array => ['status' => 'finalized'],
        static fn (array $message): array => ['status' => 'print_requested'],
        static fn (array $message): array => ['status' => 'verified', 'accepted_count' => 1],
        static fn (array $message): array => ['status' => 'election_return_ready', 'election_return_sha256' => str_repeat('a', 64)],
    );
    $response = $service->handle([
        'protocol' => '3neti.ballot-pdf', 'version' => '1.0', 'message_type' => 'session.open',
        'request_id' => 'request-1', 'nonce' => 'nonce-1',
    ]);
    expect($response['status'])->toBe('session_opened');

    $verification = $service->handle([
        'protocol' => '3neti.ballot-pdf', 'version' => '1.0', 'message_type' => 'evidence.verify',
        'request_id' => 'request-2', 'nonce' => 'nonce-2',
    ]);
    expect($verification['status'])->toBe('verified')->and($verification['accepted_count'])->toBe(1);

    $return = $service->handle([
        'protocol' => '3neti.ballot-pdf', 'version' => '1.0', 'message_type' => 'election.return',
        'request_id' => 'request-3', 'nonce' => 'nonce-3',
    ]);
    expect($return['status'])->toBe('election_return_ready')->and($return['election_return_sha256'])->toHaveLength(64);

    $reports = new BuildReports;
    expect($reports->json([]))->toContain('accepted_count')->and($reports->html([]))->toContain('<table>');
});

it('recursively verifies manifests and suppresses duplicate serials', function (): void {
    $codec = new class implements BallotPayloadCodec {
        public function encode(array $payload): array { return ['demo://'.base64_encode(json_encode($payload, JSON_THROW_ON_ERROR))]; }
        public function decode(array $scans): array { return json_decode(base64_decode(substr($scans[0], 7)), true, 512, JSON_THROW_ON_ERROR); }
    };
    $root = sys_get_temp_dir().'/ballot-pdf-verify-'.bin2hex(random_bytes(4));
    mkdir($root.'/nested', 0770, true);
    $context = new ElectionContext('demo-election', 'precinct-1', 'style-1', 'mapping-1');
    $payload = [...$context->toArray(), 'paper_ballot_serial' => 'SERIAL-1', 'candidate_codes' => ['C1']];
    $manifest = static function (string $path, array $record): void {
        file_put_contents($path, json_encode(['ballot_serial' => 'SERIAL-1', 'truth_qr_scans' => ['demo://'.base64_encode(json_encode($record, JSON_THROW_ON_ERROR))]], JSON_THROW_ON_ERROR));
    };
    $manifest($root.'/first.json', $payload);
    $manifest($root.'/nested/second.json', $payload);

    $results = (new VerifyDirectory($codec))->execute($root, $context);
    expect($results)->toHaveCount(2)
        ->and(array_map(static fn ($result): string => $result->classification, $results))->toBe(['accepted', 'duplicate_exact']);
});

it('rejects a finalized manifest when its PDF hash does not match', function (): void {
    $codec = new class implements BallotPayloadCodec {
        public function encode(array $payload): array { return ['demo://'.base64_encode(json_encode($payload, JSON_THROW_ON_ERROR))]; }
        public function decode(array $scans): array { return json_decode(base64_decode(substr($scans[0], 7)), true, 512, JSON_THROW_ON_ERROR); }
    };
    $root = sys_get_temp_dir().'/ballot-pdf-hash-'.bin2hex(random_bytes(4));
    mkdir($root.'/pdf', 0770, true);
    $context = new ElectionContext('demo-election', 'precinct-1', 'style-1', 'mapping-1');
    $payload = [...$context->toArray(), 'paper_ballot_serial' => 'SERIAL-HASH-1', 'candidate_codes' => ['C1']];
    file_put_contents($root.'/pdf/SERIAL-HASH-1.pdf', '%PDF-actual');
    file_put_contents($root.'/manifest.json', json_encode([
        'ballot_serial' => 'SERIAL-HASH-1',
        'pdf_sha256' => hash('sha256', '%PDF-other'),
        'truth_qr_scans' => ['demo://'.base64_encode(json_encode($payload, JSON_THROW_ON_ERROR))],
    ], JSON_THROW_ON_ERROR));

    $results = (new VerifyDirectory($codec))->execute($root, $context);
    expect($results[0]->classification)->toBe('invalid_context')
        ->and($results[0]->issues)->toContain('PDF hash mismatch')
        ->and($results[0]->eligible)->toBeFalse();
});
